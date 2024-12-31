### Laravel Request Profiler

![laravel-timings](https://github.com/user-attachments/assets/9ea1f374-8db2-4deb-8522-de5d872b3bd9)

Laravel Request Profiles is a package that will enable putting marks in your code to identify your application
bottlenecks.

Once installed, publish the config file by doing

```sh
php artisan vendor:publish --provider="CTOhm\LaravelRequestProfiler\Providers\TimingsServiceProvider" --tag=config
```

The service provider will autoregister a singleton `app('timings')` that you can use to push timing marks into a
dedicated message bag. We suggest starting up the timings messagebag as early as possible in the request lifecycle. For
example, in the `TrustProxies` middleware. In the same middleware you can call the method to wrap up the timings
for the current request when returning the output of `$next($request)`.

```php
    public function handle(Request $request, Closure $next)
    {
        // jumpstart the profiler at the beggining of the middleware 
        app('timings')->enabledIf(config('laravel-request-profiler.collect_timings'));
        $request->headers->set('Request-Id', (string) str()->uuid());
        app('timings')->pushTiming();

        // here goes the original content for this middleware


        // tap into the final output to wrap up the timings process
        return tap($next($request), function () use ($request) {
            app('timings')->pushTiming();
            app('timings')->process_timings($request);
        });
    }
```

Inspect the collected timings by doing

```bash
tail -f storage/logs/timings.txt
```
