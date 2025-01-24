<?php

namespace CTOhm\LaravelRequestProfiler;

use Exception;
use Illuminate\Contracts\Support\MessageProvider;
use Illuminate\Support\MessageBag as LaravelMessageBag;
use Kint\Kint;


class MessageBag extends LaravelMessageBag
{
    /**
     * All of the registered messages.
     *
     * @var array
     */
    protected $messages = [];
    const KINT_CYAN = "\033[36m";
    const KINT_RESET = "\033[0m";
    /**
     * Default format for message output.
     *
     * @var string
     */
    protected $format = ':message';
    public $enabled = false;
    public $initial_time;
    private $last_timing;

    public function __construct(array $messages = [])
    {
        $this->initial_time = round(hrtime(true) / 1000000);

        $this->last_timing = $this->initial_time;

        parent::__construct($messages);
    }
    private     function  kint_colorize(string $content, $color = '36m')
    {
        return "\x1b[" . $color . $content . static::KINT_RESET;
    }
    public function enabledIf($condition)
    {

        $this->enabled = boolval($condition);
        return $this;
    }
    /**
     * Gets the time since the last call to this method
     * @return void
     */
    private function getTimeDiff()
    {
        $current_timing = round(hrtime(true) / 1000000);
        $time_diff = round($current_timing - $this->last_timing, 4);
        $this->last_timing = $current_timing;

        return str_pad(round($time_diff / 1000, 4), 5, '0', STR_PAD_RIGHT);
    }
    /**
     * Gets the time since the last call to this method
     * @return void
     */
    private function getTimeSinceStart()
    {
        $current_timing = round(hrtime(true) / 1000000);
        return str_pad(round(($current_timing - $this->initial_time) / 1000, 4), 5, '0', STR_PAD_RIGHT);
    }
    /**
     * Add a message to the message bag.
     *
     * @param  string  $key
     * @param  string  $message
     * @return $this
     */
    public function add($key, $message)
    {
        if ($this->isUnique($key, $message)) {
            $this->messages[$key][] = $message;
        }

        return $this;
    }

    public function pushTiming($timing = null)
    {
        if (!$this->enabled || !config('laravel-request-profiler.collect_timings')) return $this;
        if (!is_object($timing)) {
            $called_from = null;
            if (is_string($timing) && preg_match('/\.php:\d+$/', $timing)) {
                $called_from = rel_path($timing);
            } else {
                Kint::$enabled_mode = Kint::MODE_TEXT;
                $called_from = $called_from ?? str()->of(
                    collect(explode(
                        PHP_EOL,
                        kreturn(50)
                    ))->get(4)
                )->replace(['.../', 'Called from '], '')->replaceMatches('/[^a-z]*([^\s]+)(\s.*)/', '$1')->replace(['(', ')'], [':', ''])
                    ->__toString();
            }
            $timing = (object)[
                'called_from' => $called_from
            ];
        }
        $timing->since_last_call = $this->getTimeDiff();
        $timing->since_start = $this->getTimeSinceStart();

        $this->add(request()->headers->get('request-id')  . $timing->since_last_call . '_' . $timing->since_start, $timing);
        return $this;
    }

    private $timings_processed = false;
    public function process_timings($request)
    {
        if ($this->timings_processed) throw new Exception('timings already processed');
        if ($this->timings_processed || $request->isIgnoredPath() || !$this->enabled || !config('laravel-request-profiler.collect_timings')) return $this;
        $this->timings_processed = true;

        $this->populatePretimings($request);

        $final_message = (string)PHP_EOL  . static::KINT_CYAN . $this->banner->implode(PHP_EOL) . PHP_EOL . $this->format_timings($request, $this->banner) . PHP_EOL . static::KINT_RESET;
        $fp = \fopen(storage_path('logs/timings.txt'), 'ab');
        \fwrite($fp, $final_message);
        \fclose($fp);
    }
    private $max_characters = 0;
    private $max_since_start = 0;
    private $pretimings = [];
    private $banner = null;
    public function populatePretimings($request)
    {
        $timings = collect($this->get(request()->headers->get('request-id') . '*'));

        $this->pretimings = $timings->values()->flatten()
            ->sortBy(
                fn($record) => floatval($record->since_start)
            )
            ->reduce(
                function ($accum, $record) {
                    if (!str_contains($record->called_from, ':')) return $accum;
                    $accum->push([
                        $record->called_from,
                        floatval($record->since_last_call),
                        floatval($record->since_start)
                    ]);
                    $this->max_characters = max($this->max_characters, strlen($record->called_from));
                    $this->max_since_start = max($this->max_since_start, $record->since_start);

                    return $accum;
                },
                collect([])
            );

        $this->max_characters = max($this->max_characters, 56);
        $padHeader = fn($values) => sprintf(
            $this->kint_colorize("|") . substr(" %s %s ", 0, $this->max_characters) . $this->kint_colorize("|"),
            str_pad($values[0], 34, ' ', STR_PAD_RIGHT),
            str_pad($values[1], $this->max_characters - 9, '.', STR_PAD_LEFT),

        );

        $this->banner = collect([
            '',
            $this->kint_colorize(str_replace(
                'X─────X',
                str_replace('-', '─', str_pad('', $this->max_characters, '-')),
                "┌──────X─────X──────────────────────┐"
            )),
            $padHeader(['spl_object_id', spl_object_id($this)]),
            $padHeader(['request_id   ', $request->headers->get('request-id')]),
            $padHeader(['path         ', $request->path()]),
            $this->kint_colorize(str_replace(
                'X─────X',
                str_replace('-', '─', str_pad('', $this->max_characters, '-')),
                '├─────┬X─────X┬─────┬───────┬───────┤'
            )),
        ]);
        return $this;
    }
    public function format_timings($request)
    {
        $pretimings = collect($this->pretimings)
            ->filter(fn($line) => str_contains($line[0], ':'))
            ->map(function ($value) {


                [$filename_with_line, $since_last_call, $since_start] = $value;

                [$filename, $line] = explode(':', trim($filename_with_line));

                $since_start = number_format($since_start, 3, '.', '');
                $since_last_call = number_format($since_last_call, 3, '.', '');
                $separator = $this->kint_colorize(' | ');
                return trim(
                    implode($separator, [
                        substr(str_pad($filename, $this->max_characters), 0, $this->max_characters - 2),
                        str_pad($line, 3, ' ', STR_PAD_LEFT),
                        $since_last_call,
                        $since_start
                    ])
                );
            })->map(function ($line, $index) {
                //if (strpos($line, '──────') !== false || $index < 2) return $line;

                $correlativo = str_pad($index, 3, '0', STR_PAD_LEFT);
                $separador =  ' | ';
                $right_side = str_pad($line, $this->max_characters + 20, ' '); // ancho max_chars + 20

                return $this->kint_colorize('| ') . $correlativo . $separador . $right_side   . $this->kint_colorize(' |');
            })
            ->unique();

        return $pretimings->push($this->kint_colorize('└─────┴─' . str_replace(
            '-',
            '─',
            str_pad('', $this->max_characters - 1, '-')
        ) . '┴─────┴───────┴───────┘'))
            ->push(
                sprintf(
                    ' %s %s',
                    str_pad('TOTAL TIME:', 28, ' ', STR_PAD_RIGHT),
                    str_pad($this->max_since_start, $this->max_characters, ' ', STR_PAD_LEFT)
                )
            )
            ->implode(PHP_EOL);
    }
    /**
     * Add a message to the message bag if the given conditional is "true".
     *
     * @param  bool  $boolean
     * @param  string  $key
     * @param  string  $message
     * @return $this
     */
    public function addIf($boolean, $key, $message)
    {
        return $boolean ? $this->add($key, $message) : $this;
    }

    /**
     * Determine if a key and message combination already exists.
     *
     * @param  string  $key
     * @param  string  $message
     * @return bool
     */
    protected function isUnique($key, $message)
    {
        $messages = (array) $this->messages;

        return !isset($messages[$key]) || !in_array($message, $messages[$key]);
    }

    /**
     * Merge a new array of messages into the message bag.
     *
     * @param  \Illuminate\Contracts\Support\MessageProvider|array  $messages
     * @return $this
     */
    public function merge($messages)
    {
        if ($messages instanceof MessageProvider) {
            $messages = $messages->getMessageBag()->getMessages();
        }

        $this->messages = array_merge_recursive($this->messages, $messages);

        return $this;
    }


    /**
     * Get all of the messages from the message bag for a given key.
     *
     * @param  string  $key
     * @param  string|null  $format
     * @return array
     */
    public function get($key, $format = null)
    {
        // If the message exists in the message bag, we will transform it and return
        // the message. Otherwise, we will check if the key is implicit & collect
        // all the messages that match the given key and output it as an array.
        if (array_key_exists($key, $this->messages)) {
            return $this->transform(
                $this->messages[$key],
                $this->checkFormat($format),
                $key
            );
        }

        if (str_contains($key, '*')) {
            return $this->getMessagesForWildcardKey($key, $format);
        }

        return [];
    }


    /**
     * Get all of the unique messages for every key in the message bag.
     *
     * @param  string|null  $format
     * @return array
     */
    public function unique($format = null)
    {
        return array_unique($this->all($format));
    }

    /**
     * Format an array of messages.
     *
     * @param  array  $messages
     * @param  string  $format
     * @param  string  $messageKey
     * @return array
     */
    protected function transform($messages, $format, $messageKey)
    {
        if ($format == ':message') {
            return (array) $messages;
        }

        return collect((array) $messages)
            ->map(function ($message) use ($format, $messageKey) {
                // We will simply spin through the given messages and transform each one
                // replacing the :message place holder with the real message allowing
                // the messages to be easily formatted to each developer's desires.
                return str_replace([':message', ':key'], [$message, $messageKey], $format);
            })->all();
    }
}
