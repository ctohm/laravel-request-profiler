<?php

/**
 * Financiatec Admin 1.4.0
 */

use Illuminate\Support\Str;
use Kint\Kint;
use Kint\Parser\BlacklistPlugin;
use Kint\Renderer\CliRenderer;
use Kint\Renderer\RichRenderer;





if (!\function_exists('rel_path')) {
    /**
     * Devuelve la ruta relativa a la ruta base.
     *
     * @param  string  $path
     * @return string
     *
     * @throws BindingResolutionException
     */
    function rel_path(string $path): string
    {
        return \str_replace(\base_path() . '/', '', $path);
    }
}
if (!function_exists('str')) {
    function str(): Str
    {
        return new  Str();
    }
}
if (!function_exists('kreturn')) {
    function kreturn(...$vars)
    {
        if (Kint::$enabled_mode === false) return '';
        CliRenderer::$cli_colors = true;
        $return = Kint::$return;
        Kint::$return = true;
        Kint::$display_called_from = true;

        return tap(Kint::dump(...$vars), function () use ($return) {
            Kint::$return = $return;
        });
    }
}

RichRenderer::$folder = false;
BlacklistPlugin::$shallow_blacklist[] = 'Psr\Container\ContainerInterface';



Kint::$aliases[] = ['ctohm\\laravelrequestprofiler\\messagebag', 'format_timings'];
Kint::$aliases[] = ['ctohm\\laravelrequestprofiler\\messagebag', 'ktime'];
Kint::$aliases[] = ['ctohm\\laravelrequestprofiler\\messagebag', 'process_timings'];
Kint::$aliases[] = ['ctohm\\laravelrequestprofiler\\messagebag', 'pushTiming'];
