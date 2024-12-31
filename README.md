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
        $request::setTrustedProxies([], $this->getTrustedHeaderNames()); // Reset trusted proxies between requests
        $this->setTrustedProxyIpAddresses($request);

        // tap into the final output to wrap up the timings process
        return tap($next($request), function () use ($request) {
            app('timings')->pushTiming();
            app('timings')->process_timings($request);
        });
    }
```

Every time you use the `app('timings')->pushTiming()` statement, a new mark will be recorded by the profiler, as if it was a breakpoint. In the image above, you can see the final output:

  - The upper section shows the spl object id of the request and its uuid, as well as the requested path. 
  - The lower section shows the file, the line where `pushTiming` was called, the incremental timing from the last call and the total time up to that point.
  - Finally, the total ovewall time is shown.

Inspect the collected timings by doing

```bash
tail -f storage/logs/timings.txt
```
