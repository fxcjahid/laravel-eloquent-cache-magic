<?php

namespace Fxcjahid\LaravelEloquentCacheMagic;

use Illuminate\Support\ServiceProvider;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Fxcjahid\LaravelEloquentCacheMagic\Console\Commands\CacheClearCommand;

/**
 * Cache Magic Service Provider
 * 
 * @package Fxcjahid\LaravelEloquentCacheMagic
 */
class CacheMagicServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap the application services.
     */
    public function boot(): void
    {
        if (!config('cache-magic.enabled', true)) {
            return;
        }

        // Register cache macro for Eloquent and Query builders
        $this->registerCacheMacros();

        // Publish configuration
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__ . '/../config/cache-magic.php' => config_path('cache-magic.php'),
            ], 'cache-magic-config');

            // Register commands
            $this->commands([
                CacheClearCommand::class,
            ]);
        }
    }

    /**
     * Register the application services.
     */
    public function register(): void
    {
        // Merge configuration
        $this->mergeConfigFrom(
            __DIR__ . '/../config/cache-magic.php',
            'cache-magic'
        );

        // Register facade
        $this->app->singleton('cache-magic', function ($app) {
            return new CacheManager();
        });
    }

    /**
     * Register cache macros on query builders
     */
    protected function registerCacheMacros(): void
    {
        // Macro for Eloquent Builder
        EloquentBuilder::macro('cache', function ($options = []) {
            // Handle different parameter formats
            if (is_numeric($options)) {
                $options = ['ttl' => $options];
            } elseif (is_string($options)) {
                $options = ['key' => $options];
            } elseif (!is_array($options)) {
                $options = [];
            }

            return new CacheQueryBuilder($this, $options);
        });

        // Macro for Query Builder
        QueryBuilder::macro('cache', function ($options = []) {
            // Handle different parameter formats
            if (is_numeric($options)) {
                $options = ['ttl' => $options];
            } elseif (is_string($options)) {
                $options = ['key' => $options];
            } elseif (!is_array($options)) {
                $options = [];
            }

            return new CacheQueryBuilder($this, $options);
        });

        // Additional convenience macros
        EloquentBuilder::macro('cacheForever', function () {
            return $this->cache(['ttl' => null]);
        });

        QueryBuilder::macro('cacheForever', function () {
            return $this->cache(['ttl' => null]);
        });

        EloquentBuilder::macro('cacheFor', function ($minutes) {
            return $this->cache(['ttl' => $minutes * 60]);
        });

        QueryBuilder::macro('cacheFor', function ($minutes) {
            return $this->cache(['ttl' => $minutes * 60]);
        });

        EloquentBuilder::macro('dontCache', function () {
            return $this; // Return original builder without caching
        });

        QueryBuilder::macro('dontCache', function () {
            return $this; // Return original builder without caching
        });
    }

    /**
     * Get the services provided by the provider.
     */
    public function provides(): array
    {
        return [
            'cache-magic',
        ];
    }
}