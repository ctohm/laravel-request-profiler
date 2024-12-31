<?php




return [
    /**
     * Enable or disable the profiler
     */
    'collect_timings' => (bool) env('COLLECT_TIMINGS', false),

    /**
     * Don't profile timings for these routes
     */
    'ignored_timing_routes' => [
        'check-session',
        'heartbeat',
    ]
];
