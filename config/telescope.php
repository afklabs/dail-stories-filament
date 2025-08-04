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
    | Telescope Watchers - ALL ENABLED
    |--------------------------------------------------------------------------
    |
    | ALL watchers are enabled for maximum debugging capability.
    | ⚠️  WARNING: This will generate A LOT of data - use only in development!
    |
    */

    'watchers' => [
        // 🚨 Core Application Watchers - ALWAYS ENABLED
        Watchers\RequestWatcher::class => [
            'enabled' => true,
            'size_limit' => 128, // Increased for full debugging
            'ignore_http_methods' => [], // Track ALL HTTP methods
            'ignore_status_codes' => [], // Track ALL status codes
        ],

        Watchers\QueryWatcher::class => [
            'enabled' => true,
            'ignore_packages' => false, // Show ALL queries including vendor
            'ignore_paths' => [], // Show ALL paths
            'slow' => 10, // Log queries slower than 10ms (very sensitive)
        ],

        Watchers\ExceptionWatcher::class => true,

        Watchers\LogWatcher::class => [
            'enabled' => true,
            'level' => 'debug', // Capture ALL log levels
        ],

        // 🔍 Model & Database Watchers - FULLY ENABLED
        Watchers\ModelWatcher::class => [
            'enabled' => true,
            'events' => ['eloquent.*'], // ALL Eloquent events
            'hydrations' => true, // Track model hydrations
        ],

        Watchers\CacheWatcher::class => [
            'enabled' => true,
            'hidden' => [], // Show ALL cache operations
            'ignore' => [], // Don't ignore anything
        ],

        Watchers\RedisWatcher::class => true,

        // 🚀 Background Processing Watchers - ALL ENABLED
        Watchers\JobWatcher::class => true,

        Watchers\BatchWatcher::class => true,

        Watchers\ScheduleWatcher::class => true,

        Watchers\CommandWatcher::class => [
            'enabled' => true,
            'ignore' => [], // Show ALL commands
        ],

        // 📧 Communication Watchers - ALL ENABLED
        Watchers\MailWatcher::class => true,

        Watchers\NotificationWatcher::class => true,

        // 🔐 Security & Authorization Watchers - ALL ENABLED
        Watchers\GateWatcher::class => [
            'enabled' => true,
            'ignore_abilities' => [], // Show ALL abilities
            'ignore_packages' => false, // Show package gates too
            'ignore_paths' => [], // Show ALL paths
        ],

        // 🌐 External Communication Watchers - ALL ENABLED
        Watchers\ClientRequestWatcher::class => true,

        // 🎭 View & Frontend Watchers - ALL ENABLED
        Watchers\ViewWatcher::class => true,

        // 🔧 Development Tools - ALL ENABLED
        Watchers\DumpWatcher::class => [
            'enabled' => true,
            'always' => true, // Always capture dumps
        ],

        // 📊 Event System - ALL ENABLED
        Watchers\EventWatcher::class => [
            'enabled' => true,
            'ignore' => [], // Show ALL events
        ],
    ],
];
