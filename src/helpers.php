<?php

use Fxcjahid\LaravelEloquentCacheMagic\CacheQueryBuilder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Auth;

if (! function_exists('cache_magic')) {
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

if (! function_exists('cache_remember')) {
    /**
     * Cache a callback result
     *
     * @param array $options
     * @param callable $callback
     * @return mixed
     */
    function cache_remember(array $options, callable $callback)
    {
        return CacheQueryBuilder::callback($options, $callback);
    }
}

if (! function_exists('cache_clear_tags')) {
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

if (! function_exists('cache_clear_model')) {
    /**
     * Clear cache for a specific model
     * 
     * @param string $modelClass
     * @return bool
     */
    function cache_clear_model(string $modelClass): bool
    {
        $tag = 'model:'.strtolower(class_basename($modelClass));
        return cache_clear_tags([$tag]);
    }
}

if (! function_exists('cache_statistics')) {
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

if (! function_exists('cache_health')) {
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

if (! function_exists('cache_supports_tags')) {
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

if (! function_exists('cache_warm_model')) {
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

if (! function_exists('cache_clear_user')) {
    /**
     * Clear all cache for a specific user
     * 
     * @param int|null $userId If null, clears current user's cache
     * @return bool
     */
    function cache_clear_user(?int $userId = null): bool
    {
        $userId = $userId ?: Auth::id();

        if (! $userId) {
            return false;
        }

        if (Cache::supportsTags()) {
            Cache::tags(['user:'.$userId])->flush();
            return true;
        }

        return false;
    }
}

if (! function_exists('cache_clear_guest')) {
    /**
     * Clear cache for current guest session
     * 
     * @param string|null $guestId Optional specific guest ID
     * @return bool
     */
    function cache_clear_guest(?string $guestId = null): bool
    {
        if (! $guestId) {
            // Try to get current guest ID from session
            $guestId = session()->getId();
        }

        if (! $guestId) {
            return false;
        }

        if (Cache::supportsTags()) {
            Cache::tags(['guest:'.$guestId])->flush();
            return true;
        }

        return false;
    }
}

if (! function_exists('cache_clear_all_users')) {
    /**
     * Clear cache for all authenticated users
     * Warning: This could be resource intensive with many users
     * 
     * @return bool
     */
    function cache_clear_all_users(): bool
    {
        if (! Cache::supportsTags()) {
            return false;
        }

        // This would need to iterate through all user IDs
        // For now, we'll clear common user-related tags
        Cache::tags(['users'])->flush();

        return true;
    }
}

if (! function_exists('cache_clear_all_guests')) {
    /**
     * Clear all guest cache entries
     * 
     * @return bool
     */
    function cache_clear_all_guests(): bool
    {
        if (! Cache::supportsTags()) {
            return false;
        }

        // Clear all guest-prefixed tags
        // Note: This might not clear all individual guest tags
        Cache::tags(['guests'])->flush();

        return true;
    }
}