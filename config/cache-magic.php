<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Cache Magic Configuration
    |--------------------------------------------------------------------------
    |
    | Configure the behavior of the Laravel Eloquent Cache Magic package.
    | These settings control caching behavior, invalidation, monitoring, and more.
    |
    */

    /*
    |--------------------------------------------------------------------------
    | Enable/Disable Cache Magic
    |--------------------------------------------------------------------------
    |
    | Master switch to enable or disable all caching functionality.
    | Useful for debugging or temporarily disabling cache in development.
    |
    */
    'enabled' => env('CACHE_MAGIC_ENABLED', true),

    /*
    |--------------------------------------------------------------------------
    | Default Cache TTL (Time To Live)
    |--------------------------------------------------------------------------
    |
    | Default cache duration in seconds. Can be overridden per query.
    | Default: 3600 seconds (1 hour)
    |
    */
    'default_ttl' => env('CACHE_MAGIC_DEFAULT_TTL', 3600),

    /*
    |--------------------------------------------------------------------------
    | Cache Version
    |--------------------------------------------------------------------------
    |
    | Version string prepended to all cache keys. Increment this to
    | invalidate all existing cache entries without flushing the cache.
    |
    */
    'version' => env('CACHE_MAGIC_VERSION', '1'),

    /*
    |--------------------------------------------------------------------------
    | Global Cache Tags
    |--------------------------------------------------------------------------
    |
    | Tags that will be applied to all cached queries. Useful for
    | invalidating all application cache at once.
    | Note: Only works with Redis or Memcached cache drivers.
    |
    */
    'global_tags' => [
        'app',
        // Add more global tags as needed
    ],

    /*
    |--------------------------------------------------------------------------
    | Auto Cache Invalidation
    |--------------------------------------------------------------------------
    |
    | Automatically invalidate cache when models are created, updated, or deleted.
    | This ensures cache consistency with your database.
    |
    */
    'auto_invalidation' => [
        'enabled' => env('CACHE_MAGIC_AUTO_INVALIDATION', true),
        'events' => [
            'created' => true,
            'updated' => true,
            'deleted' => true,
            'restored' => true,
            'forceDeleted' => true,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Adaptive TTL
    |--------------------------------------------------------------------------
    |
    | Enable adaptive TTL that adjusts cache duration based on access frequency.
    | Frequently accessed data gets longer TTL, rarely accessed gets shorter.
    |
    */
    'adaptive_ttl' => [
        'enabled' => env('CACHE_MAGIC_ADAPTIVE_TTL', false),
        'min_ttl' => 300,     // 5 minutes minimum
        'max_ttl' => 86400,   // 24 hours maximum
        'thresholds' => [
            'hot' => 100,     // Access count for hot data
            'warm' => 50,     // Access count for warm data
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Cache Statistics
    |--------------------------------------------------------------------------
    |
    | Track cache hit/miss rates and other statistics.
    | Statistics are stored in cache with configurable TTL.
    |
    */
    'statistics' => [
        'enabled' => env('CACHE_MAGIC_STATISTICS', true),
        'ttl' => 86400,       // Statistics retention period (24 hours)
        'detailed' => false,  // Track detailed per-key statistics
    ],

    /*
    |--------------------------------------------------------------------------
    | Debug Mode
    |--------------------------------------------------------------------------
    |
    | Enable detailed logging of all cache operations.
    | Warning: This can generate a lot of log entries!
    |
    */
    'debug' => env('CACHE_MAGIC_DEBUG', false),
    'log_channel' => env('CACHE_MAGIC_LOG_CHANNEL', 'single'),

    /*
    |--------------------------------------------------------------------------
    | Cache Warming
    |--------------------------------------------------------------------------
    |
    | Configuration for cache warming functionality.
    | Define queries that should be pre-cached.
    |
    */
    'warming' => [
        'enabled' => env('CACHE_MAGIC_WARMING_ENABLED', false),
        'schedule' => '0 * * * *', // Hourly by default
        'queries' => [
            // Example:
            // [
            //     'model' => \App\Models\User::class,
            //     'method' => 'where',
            //     'args' => ['active', true],
            //     'cache_method' => 'get',
            //     'ttl' => 7200,
            //     'tags' => ['users', 'active'],
            // ],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Async Cache Refresh
    |--------------------------------------------------------------------------
    |
    | Configuration for asynchronous cache refresh via queued jobs.
    |
    */
    'async' => [
        'enabled' => env('CACHE_MAGIC_ASYNC_ENABLED', true),
        'queue' => env('CACHE_MAGIC_ASYNC_QUEUE', 'default'),
        'connection' => env('CACHE_MAGIC_ASYNC_CONNECTION', null),
    ],

    /*
    |--------------------------------------------------------------------------
    | Cache Middleware
    |--------------------------------------------------------------------------
    |
    | HTTP cache middleware configuration for API responses.
    |
    */
    'middleware' => [
        'enabled' => env('CACHE_MAGIC_MIDDLEWARE_ENABLED', false),
        'ttl' => 300,         // 5 minutes for API responses
        'methods' => ['GET'], // HTTP methods to cache
        'exclude_params' => [ // Query parameters to exclude from cache key
            'token',
            'api_key',
            '_',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Redis/Memcached Configuration
    |--------------------------------------------------------------------------
    |
    | Specific configuration for Redis and Memcached drivers.
    | These drivers support cache tagging for better invalidation.
    |
    */
    'redis' => [
        'connection' => env('CACHE_MAGIC_REDIS_CONNECTION', 'cache'),
        'lock_connection' => env('CACHE_MAGIC_REDIS_LOCK_CONNECTION', 'default'),
        'prefix' => env('CACHE_MAGIC_REDIS_PREFIX', 'cache_magic'),
    ],

    'memcached' => [
        'persistent_id' => env('CACHE_MAGIC_MEMCACHED_PERSISTENT_ID', null),
        'sasl' => [
            env('CACHE_MAGIC_MEMCACHED_USERNAME'),
            env('CACHE_MAGIC_MEMCACHED_PASSWORD'),
        ],
        'options' => [
            // Memcached::OPT_CONNECT_TIMEOUT => 2000,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Cache Health Check
    |--------------------------------------------------------------------------
    |
    | Configuration for cache health monitoring.
    |
    */
    'health' => [
        'enabled' => env('CACHE_MAGIC_HEALTH_ENABLED', true),
        'threshold' => [
            'hit_rate' => 0.5,      // Minimum acceptable hit rate
            'response_time' => 100, // Maximum response time in ms
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Excluded Models
    |--------------------------------------------------------------------------
    |
    | Models that should not use automatic caching.
    | Useful for models with sensitive or frequently changing data.
    |
    */
    'excluded_models' => [
        // \App\Models\Session::class,
        // \App\Models\Job::class,
    ],

    /*
    |--------------------------------------------------------------------------
    | Custom Cache Drivers
    |--------------------------------------------------------------------------
    |
    | Register custom cache drivers for specific use cases.
    |
    */
    'custom_drivers' => [
        // 'my-driver' => \App\Cache\MyCustomDriver::class,
    ],
];