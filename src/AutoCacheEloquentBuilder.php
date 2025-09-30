<?php

namespace Fxcjahid\LaravelEloquentCacheMagic;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Cache;
use Fxcjahid\LaravelEloquentCacheMagic\Monitoring\CacheStatistics;

/**
 * Auto-Caching Eloquent Builder
 *
 * Automatically caches all query results without requiring explicit ->cache() calls
 */
class AutoCacheEloquentBuilder extends Builder
{
    /**
     * List of methods that should trigger caching
     */
    protected array $cacheableMethods = [
        'get',
        'first',
        'firstOrFail',
        'find',
        'findOrFail',
        'findMany',
        'pluck',
        'count',
        'sum',
        'avg',
        'max',
        'min',
        'exists',
        'doesntExist',
    ];

    /**
     * List of methods that should NOT be cached
     */
    protected array $nonCacheableMethods = [
        'insert',
        'update',
        'delete',
        'truncate',
        'create',
        'forceCreate',
        'save',
        'touch',
        'restore',
        'forceDelete',
    ];

    /**
     * Flag to disable caching for current query
     */
    protected bool $skipCache = false;

    /**
     * Execute the query and cache the result if applicable
     */
    public function __call($method, $parameters)
    {
        // Check if auto-cache is enabled globally
        if (!config('cache-magic.auto_cache.enabled', false)) {
            return parent::__call($method, $parameters);
        }

        // Check if this model has auto-cache enabled
        $model = $this->getModel();
        if (property_exists($model, 'autoCache') && $model->autoCache === false) {
            return parent::__call($method, $parameters);
        }

        // Skip caching if explicitly disabled for this query
        if ($this->skipCache) {
            return parent::__call($method, $parameters);
        }

        // Check if method should be cached
        if (!in_array($method, $this->cacheableMethods)) {
            return parent::__call($method, $parameters);
        }

        // Generate cache configuration
        $cacheConfig = $this->getCacheConfiguration($method);

        // Build cache key
        $cacheKey = $this->buildCacheKey($method, $parameters);

        // Try to get from cache
        if (Cache::supportsTags() && !empty($cacheConfig['tags'])) {
            $cached = Cache::tags($cacheConfig['tags'])->get($cacheKey);
        } else {
            $cached = Cache::get($cacheKey);
        }

        if ($cached !== null) {
            $this->logCacheHit($cacheKey);
            return $cached;
        }

        // Execute query
        $result = parent::__call($method, $parameters);

        // Cache the result
        if ($result !== null) {
            if (Cache::supportsTags() && !empty($cacheConfig['tags'])) {
                Cache::tags($cacheConfig['tags'])->put($cacheKey, $result, $cacheConfig['ttl']);
            } else {
                Cache::put($cacheKey, $result, $cacheConfig['ttl']);
            }
            $this->logCacheMiss($cacheKey);
        }

        return $result;
    }

    /**
     * Disable caching for this query
     */
    public function withoutCache(): self
    {
        $this->skipCache = true;
        return $this;
    }

    /**
     * Alias for withoutCache
     */
    public function fresh(): self
    {
        return $this->withoutCache();
    }

    /**
     * Build cache key for the query
     */
    protected function buildCacheKey(string $method, array $parameters): string
    {
        $model = $this->getModel();
        $sql = $this->toSql();
        $bindings = $this->getBindings();

        // Create a unique key based on model, method, SQL, and bindings
        $key = sprintf(
            'auto:%s:%s:%s',
            strtolower(class_basename($model)),
            $method,
            md5($sql . serialize($bindings) . serialize($parameters))
        );

        // Add version if configured
        if ($version = config('cache-magic.version')) {
            $key = "v{$version}:{$key}";
        }

        return $key;
    }

    /**
     * Get cache configuration for the model
     */
    protected function getCacheConfiguration(string $method): array
    {
        $model = $this->getModel();

        // Get TTL
        $ttl = config('cache-magic.auto_cache.ttl', 3600);
        if (property_exists($model, 'cacheExpiry') && $model->cacheExpiry !== null) {
            $ttl = $model->cacheExpiry;
        }

        // Adjust TTL based on method type
        $ttl = $this->adjustTtlForMethod($method, $ttl);

        // Get tags
        $tags = [];
        if (method_exists($model, 'getCacheTags')) {
            $tags = $model->getCacheTags();
        }

        // Add model-specific tag
        $tags[] = 'model:' . strtolower(class_basename($model));

        // Add global tags
        $globalTags = config('cache-magic.global_tags', []);
        $tags = array_unique(array_merge($tags, $globalTags));

        return [
            'ttl' => $ttl,
            'tags' => $tags,
        ];
    }

    /**
     * Adjust TTL based on query method
     */
    protected function adjustTtlForMethod(string $method, int $baseTtl): int
    {
        // Ensure we have a valid base TTL
        $baseTtl = max(1, $baseTtl);

        // Shorter TTL for aggregate functions
        $aggregateMethods = ['count', 'sum', 'avg', 'max', 'min', 'exists', 'doesntExist'];
        if (in_array($method, $aggregateMethods)) {
            return min($baseTtl, config('cache-magic.auto_cache.aggregate_ttl', 300));
        }

        // Longer TTL for find operations
        $findMethods = ['find', 'findOrFail', 'findMany'];
        if (in_array($method, $findMethods)) {
            return config('cache-magic.auto_cache.find_ttl', $baseTtl);
        }

        return $baseTtl;
    }

    /**
     * Log cache hit
     */
    protected function logCacheHit(string $key): void
    {
        if (config('cache-magic.debug', false)) {
            logger()->debug('Auto-cache hit', [
                'key' => $key,
                'model' => get_class($this->getModel()),
            ]);
        }

        // Update statistics if enabled
        if (config('cache-magic.statistics.enabled', true)) {
            try {
                app(CacheStatistics::class)->recordHit($key);
            } catch (\Exception $e) {
                // Silently fail if statistics service not available
            }
        }
    }

    /**
     * Log cache miss
     */
    protected function logCacheMiss(string $key): void
    {
        if (config('cache-magic.debug', false)) {
            logger()->debug('Auto-cache miss', [
                'key' => $key,
                'model' => get_class($this->getModel()),
            ]);
        }

        // Update statistics if enabled
        if (config('cache-magic.statistics.enabled', true)) {
            try {
                app(CacheStatistics::class)->recordMiss($key);
            } catch (\Exception $e) {
                // Silently fail if statistics service not available
            }
        }
    }
}