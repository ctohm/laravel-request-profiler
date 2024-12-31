<?php

namespace CTOhm\LaravelRequestProfiler\Providers;

use CTOhm\LaravelRequestProfiler\MessageBag;
use Illuminate\Http\Request;
use Illuminate\Support\ServiceProvider;

class TimingsServiceProvider extends ServiceProvider
{


    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton('timings', static function () {
            return new MessageBag();
        });

        $this->mergeConfigFrom(__DIR__ . '/../config/laravel-request-profiler.php', 'laravel-request-profiler');
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {


        /**
         * Returns true is request path is deemed to be ignored for timings collection
         */
        Request::macro('isIgnoredPath', function (): bool {
            foreach (config('laravel-request-profiler.ignored_timing_routes', []) as $path) {
                if ($this->is($path)) return true;
            }
            return (!config('laravel-request-profiler.collect_timings') || $this->is('js/*')
                || $this->is('css/*') || $this->is('images/*') || $this->is('fonts/*'));
        });

        $this->publishes([
            __DIR__ . '/../config/laravel-request-profiler.php' => config_path('laravel-request-profiler.php'),
        ], 'config');
    }
}
