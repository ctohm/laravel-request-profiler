<?php

namespace CTOhm\LaravelRequestProfiler\Providers;

use CTOhm\LaravelRequestProfiler\MessageBag;
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

        $this->publishes([
            __DIR__ . '/../config/laravel-request-profiler.php' => config_path('laravel-request-profiler.php'),
        ], 'config');
    }
}
