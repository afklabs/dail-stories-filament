<?php

use Laravel\Telescope\Http\Middleware\Authorize;
use Laravel\Telescope\Watchers;

return [

    /*
    |--------------------------------------------------------------------------
    | Telescope Master Switch
    |--------------------------------------------------------------------------
    |
    | This option may be used to disable all Telescope watchers regardless
    | of their individual configuration, which simply provides a single
    | and convenient way to enable or disable Telescope data storage.
    |
    */

    'enabled' => env('TELESCOPE_ENABLED', true),

    /*
    |--------------------------------------------------------------------------
    | Telescope Domain
    |--------------------------------------------------------------------------
    |
    | This is the subdomain where Telescope will be accessible from. If the
    | setting is null, Telescope will reside under the same domain as the
    | application. Otherwise, this value will be used as the subdomain.
    |
    */

    'domain' => env('TELESCOPE_DOMAIN'),

    /*
    |--------------------------------------------------------------------------
    | Telescope Path
    |--------------------------------------------------------------------------
    |
    | This is the URI path where Telescope will be accessible from. Feel free
    | to change this path to anything you like. Note that the URI will not
    | affect the paths of its internal API that aren't exposed to users.
    |
    */

    'path' => env('TELESCOPE_PATH', 'telescope'),

    /*
    |--------------------------------------------------------------------------
    | Telescope Storage Driver
    |--------------------------------------------------------------------------
    |
    | This configuration options determines the storage driver that will
    | be used to store Telescope's data. In addition, you may set any
    | custom options as needed by the particular driver you choose.
    |
    */

    'driver' => env('TELESCOPE_DRIVER', 'database'),

    'storage' => [
        'database' => [
            'connection' => env('DB_CONNECTION', 'sqlite'), // Matches your project's default
            'chunk' => 1000,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Telescope Queue
    |--------------------------------------------------------------------------
    |
    | This configuration options determines the queue connection and queue
    | which will be used to process ProcessPendingUpdate jobs. This can
    | be changed if you would prefer to use a non-default connection.
    |
    */

    'queue' => [
        'connection' => env('TELESCOPE_QUEUE_CONNECTION', null),
        'queue' => env('TELESCOPE_QUEUE', null),
        'delay' => env('TELESCOPE_QUEUE_DELAY', 10),
    ],

    /*
    |--------------------------------------------------------------------------
    | Telescope Route Middleware
    |--------------------------------------------------------------------------
    |
    | These middleware will be assigned to every Telescope route, giving you
    | the chance to add your own middleware to this list or change any of
    | the existing middleware. Or, you can simply stick with this list.
    |
    */

    'middleware' => [
        'web',
        Authorize::class,
    ],

    /*
    |--------------------------------------------------------------------------
    | Allowed / Ignored Paths & Commands
    |--------------------------------------------------------------------------
    |
    | The following array lists the URI paths and Artisan commands that will
    | not be watched by Telescope. In addition to this list, some Laravel
    | commands, like migrations and queue commands, are always ignored.
    |
    */

    'only_paths' => [
        'api/*', // 🎯 Focus on API routes for your story platform
    ],

    'ignore_paths' => [
        'livewire*',
        'nova-api*',
        'pulse*',
        'telescope*', // Don't watch Telescope itself
        '_ignition*', // Ignore debug pages
        'favicon.ico',
        '*.css',
        '*.js',
        '*.map',
    ],

    'ignore_commands' => [
        'telescope:*',
        'schedule:*',
        'horizon:*',
        'queue:*',
    ],

    /*
    |--------------------------------------------------------------------------
    | Data Pruning Configuration
    |--------------------------------------------------------------------------
    |
    | Configure automatic pruning to prevent telescope_entries table from
    | growing too large and impacting performance.
    |
    */

    'pruning' => [
        'enabled' => env('TELESCOPE_PRUNING_ENABLED', true),
        'days' => env('TELESCOPE_PRUNING_DAYS', 7), // Keep 1 week of data
        'lottery' => [2, 100], // 2% chance to prune on each request
    ],

    /*
    |--------------------------------------------------------------------------
    | Telescope Watchers
    |--------------------------------------------------------------------------
    |
    | The following array lists the "watchers" that will be registered with
    | Telescope. The watchers gather the application's profile data when
    | a request or task is executed. Optimized for story platform debugging.
    |
    */

    'watchers' => [
        // 🚨 HIGH PRIORITY - Essential for API debugging
        Watchers\RequestWatcher::class => [
            'enabled' => env('TELESCOPE_REQUEST_WATCHER', true),
            'size_limit' => env('TELESCOPE_RESPONSE_SIZE_LIMIT', 64), // KB
            'ignore_http_methods' => [], // Track all HTTP methods
            'ignore_status_codes' => [404, 410], // Skip common not found errors
        ],

        // 🚨 HIGH PRIORITY - Database query optimization
        Watchers\QueryWatcher::class => [
            'enabled' => env('TELESCOPE_QUERY_WATCHER', true),
            'ignore_packages' => true, // Ignore vendor package queries
            'ignore_paths' => [
                'telescope*', // Don't log Telescope's own queries
            ],
            'slow' => env('TELESCOPE_SLOW_QUERY_THRESHOLD', 50), // Log queries slower than 50ms
        ],

        // 🚨 HIGH PRIORITY - Cache performance monitoring
        Watchers\CacheWatcher::class => [
            'enabled' => env('TELESCOPE_CACHE_WATCHER', true),
            'hidden' => [], // Show all cache operations
            'ignore' => [
                'telescope:*', // Ignore Telescope cache operations
            ],
        ],

        // 🚨 HIGH PRIORITY - Redis monitoring (if used)
        Watchers\RedisWatcher::class => env('TELESCOPE_REDIS_WATCHER', true),

        // 🔍 MEDIUM PRIORITY - Application debugging
        Watchers\ExceptionWatcher::class => env('TELESCOPE_EXCEPTION_WATCHER', true),

        Watchers\LogWatcher::class => [
            'enabled' => env('TELESCOPE_LOG_WATCHER', true),
            'level' => env('TELESCOPE_LOG_LEVEL', 'error'), // Only capture error+ logs
        ],

        Watchers\ModelWatcher::class => [
            'enabled' => env('TELESCOPE_MODEL_WATCHER', true),
            'events' => ['eloquent.*'], // Track all Eloquent events
            'hydrations' => true, // Track model hydrations
        ],

        // Job monitoring (important for story processing)
        Watchers\JobWatcher::class => env('TELESCOPE_JOB_WATCHER', true),

        Watchers\CommandWatcher::class => [
            'enabled' => env('TELESCOPE_COMMAND_WATCHER', true),
            'ignore' => [
                'telescope:*',
                'schedule:*',
                'horizon:*',
                'queue:*',
            ],
        ],

        // Event tracking
        Watchers\EventWatcher::class => [
            'enabled' => env('TELESCOPE_EVENT_WATCHER', true),
            'ignore' => [
                'Illuminate\Log\Events\MessageLogged',
                'Illuminate\Queue\Events\*',
            ],
        ],

        // Authorization debugging
        Watchers\GateWatcher::class => [
            'enabled' => env('TELESCOPE_GATE_WATCHER', true),
            'ignore_abilities' => [],
            'ignore_packages' => true,
            'ignore_paths' => [
                'telescope*',
            ],
        ],

        // 📧 LOW PRIORITY - Enable only if you send emails
        Watchers\MailWatcher::class => env('TELESCOPE_MAIL_WATCHER', false),

        Watchers\NotificationWatcher::class => env('TELESCOPE_NOTIFICATION_WATCHER', false),

        // Development tools
        Watchers\DumpWatcher::class => [
            'enabled' => env('TELESCOPE_DUMP_WATCHER', true),
            'always' => env('TELESCOPE_DUMP_WATCHER_ALWAYS', false),
        ],

        Watchers\ViewWatcher::class => env('TELESCOPE_VIEW_WATCHER', false), // Disable for API-only apps

        // Advanced watchers
        Watchers\BatchWatcher::class => env('TELESCOPE_BATCH_WATCHER', true),
        Watchers\ClientRequestWatcher::class => env('TELESCOPE_CLIENT_REQUEST_WATCHER', false), // Enable if using HTTP client
        Watchers\ScheduleWatcher::class => env('TELESCOPE_SCHEDULE_WATCHER', true),
    ],
];
