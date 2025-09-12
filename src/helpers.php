<?php

use Fxcjahid\LaravelEloquentCacheMagic\CacheQueryBuilder;
use Illuminate\Support\Facades\Cache;

if (!function_exists('cache_magic')) {
    /**
     * Get the Cache Magic instance
     * 
     * @return \Fxcjahid\LaravelEloquentCacheMagic\CacheManager
     */
    function cache_magic()
    {
        return app('cache-magic');
    }
}

if (!function_exists('cache_remember')) {
    /**
     * Cache a callback result
     * 
     * @param callable $callback
     * @param array $options
     * @return mixed
     */
    function cache_remember(callable $callback, array $options = [])
    {
        return CacheQueryBuilder::callback($callback, $options);
    }
}

if (!function_exists('cache_clear_tags')) {
    /**
     * Clear cache by tags
     * 
     * @param array $tags
     * @return bool
     */
    function cache_clear_tags(array $tags): bool
    {
        return CacheQueryBuilder::clearByTags($tags);
    }
}

if (!function_exists('cache_clear_model')) {
    /**
     * Clear cache for a specific model
     * 
     * @param string $modelClass
     * @return bool
     */
    function cache_clear_model(string $modelClass): bool
    {
        $tag = 'model:' . strtolower(class_basename($modelClass));
        return cache_clear_tags([$tag]);
    }
}

if (!function_exists('cache_statistics')) {
    /**
     * Get cache statistics
     * 
     * @return array
     */
    function cache_statistics(): array
    {
        return app(\Fxcjahid\LaravelEloquentCacheMagic\Monitoring\CacheStatistics::class)->getGlobalStats();
    }
}

if (!function_exists('cache_health')) {
    /**
     * Check cache health
     * 
     * @return array
     */
    function cache_health(): array
    {
        return app(\Fxcjahid\LaravelEloquentCacheMagic\Monitoring\CacheHealth::class)->check();
    }
}

if (!function_exists('cache_supports_tags')) {
    /**
     * Check if cache driver supports tags
     * 
     * @return bool
     */
    function cache_supports_tags(): bool
    {
        return Cache::supportsTags();
    }
}

if (!function_exists('cache_warm_model')) {
    /**
     * Warm up cache for a model
     * 
     * @param string $modelClass
     * @param array $queries
     * @return void
     */
    function cache_warm_model(string $modelClass, array $queries = []): void
    {
        foreach ($queries as $query) {
            $builder = $modelClass::query();
            
            if (isset($query['method'])) {
                $builder = $builder->{$query['method']}(...($query['args'] ?? []));
            }
            
            $builder->cache($query['options'] ?? [])->get();
        }
    }
}