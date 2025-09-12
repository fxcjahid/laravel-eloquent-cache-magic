<?php

namespace Fxcjahid\LaravelEloquentCacheMagic;

use Illuminate\Support\Facades\Cache;
use Fxcjahid\LaravelEloquentCacheMagic\Monitoring\CacheStatistics;
use Fxcjahid\LaravelEloquentCacheMagic\Monitoring\CacheHealth;

/**
 * Cache Manager
 * 
 * Central manager for all cache operations and utilities.
 * 
 * @package Fxcjahid\LaravelEloquentCacheMagic
 */
class CacheManager
{
    /**
     * Cache statistics instance
     */
    protected CacheStatistics $statistics;
    
    /**
     * Cache health instance
     */
    protected CacheHealth $health;
    
    /**
     * Constructor
     */
    public function __construct()
    {
        $this->statistics = app(CacheStatistics::class);
        $this->health = app(CacheHealth::class);
    }
    
    /**
     * Get cache statistics
     */
    public function statistics(): CacheStatistics
    {
        return $this->statistics;
    }
    
    /**
     * Get cache health monitor
     */
    public function health(): CacheHealth
    {
        return $this->health;
    }
    
    /**
     * Clear cache by tags
     */
    public function clearTags(array $tags): bool
    {
        if (!Cache::supportsTags()) {
            return false;
        }
        
        Cache::tags($tags)->flush();
        return true;
    }
    
    /**
     * Clear cache by model
     */
    public function clearModel(string $modelClass): bool
    {
        $tag = 'model:' . strtolower(class_basename($modelClass));
        return $this->clearTags([$tag]);
    }
    
    /**
     * Clear all cache
     */
    public function clearAll(): bool
    {
        Cache::flush();
        return true;
    }
    
    /**
     * Warm cache for a model
     */
    public function warmModel(string $modelClass, array $queries = []): void
    {
        foreach ($queries as $query) {
            $this->warmQuery($modelClass, $query);
        }
    }
    
    /**
     * Warm a specific query
     */
    public function warmQuery(string $modelClass, array $config): void
    {
        $query = $modelClass::query();
        
        // Apply query configuration
        if (isset($config['method'])) {
            $query = $query->{$config['method']}(...($config['args'] ?? []));
        }
        
        if (isset($config['with'])) {
            $query = $query->with($config['with']);
        }
        
        // Cache the query
        $cacheMethod = $config['cache_method'] ?? 'get';
        $query->cache($config['options'] ?? [])->$cacheMethod();
    }
    
    /**
     * Get cache status
     */
    public function status(): array
    {
        return [
            'enabled' => config('cache-magic.enabled', true),
            'driver' => config('cache.default'),
            'supports_tags' => Cache::supportsTags(),
            'statistics' => $this->statistics->getGlobalStats(),
            'health' => $this->health->check(),
        ];
    }
    
    /**
     * Check if cache is enabled
     */
    public function isEnabled(): bool
    {
        return config('cache-magic.enabled', true);
    }
    
    /**
     * Enable cache
     */
    public function enable(): void
    {
        config(['cache-magic.enabled' => true]);
    }
    
    /**
     * Disable cache
     */
    public function disable(): void
    {
        config(['cache-magic.enabled' => false]);
    }
    
    /**
     * Get cache configuration
     */
    public function config(string $key = null, $default = null)
    {
        if ($key === null) {
            return config('cache-magic');
        }
        
        return config("cache-magic.{$key}", $default);
    }
    
    /**
     * Set cache configuration
     */
    public function setConfig(string $key, $value): void
    {
        config(["cache-magic.{$key}" => $value]);
    }
    
    /**
     * Get cache driver info
     */
    public function driverInfo(): array
    {
        $driver = config('cache.default');
        
        return [
            'driver' => $driver,
            'supports_tags' => Cache::supportsTags(),
            'connection' => config("cache.stores.{$driver}"),
            'recommended' => in_array($driver, ['redis', 'memcached']),
        ];
    }
    
    /**
     * Remember a value in cache
     */
    public function remember(string $key, $ttl, callable $callback, array $tags = [])
    {
        if (!empty($tags) && Cache::supportsTags()) {
            return Cache::tags($tags)->remember($key, $ttl, $callback);
        }
        
        return Cache::remember($key, $ttl, $callback);
    }
    
    /**
     * Forget a cached value
     */
    public function forget(string $key, array $tags = []): bool
    {
        if (!empty($tags) && Cache::supportsTags()) {
            return Cache::tags($tags)->forget($key);
        }
        
        return Cache::forget($key);
    }
    
    /**
     * Flush cache by pattern (Redis only)
     */
    public function flushPattern(string $pattern): int
    {
        if (config('cache.default') !== 'redis') {
            throw new \RuntimeException('Pattern flushing only works with Redis driver');
        }
        
        $keys = \Illuminate\Support\Facades\Redis::keys($pattern);
        
        if (empty($keys)) {
            return 0;
        }
        
        return \Illuminate\Support\Facades\Redis::del($keys);
    }
    
    /**
     * Get cache size
     */
    public function size(): array
    {
        $driver = config('cache.default');
        
        switch ($driver) {
            case 'redis':
                $info = \Illuminate\Support\Facades\Redis::info('memory');
                return [
                    'driver' => 'redis',
                    'size' => $info['used_memory_human'] ?? 'N/A',
                    'peak' => $info['used_memory_peak_human'] ?? 'N/A',
                ];
                
            case 'memcached':
                // Would need memcached stats
                return [
                    'driver' => 'memcached',
                    'size' => 'N/A',
                ];
                
            case 'database':
                $count = \DB::table('cache')->count();
                return [
                    'driver' => 'database',
                    'entries' => $count,
                ];
                
            default:
                return [
                    'driver' => $driver,
                    'size' => 'N/A',
                ];
        }
    }
    
    /**
     * Optimize cache
     */
    public function optimize(): array
    {
        $results = [];
        
        // Clear expired entries
        if (config('cache.default') === 'database') {
            $deleted = \DB::table('cache')
                ->where('expiration', '<', time())
                ->delete();
            $results['expired_cleared'] = $deleted;
        }
        
        // Reset statistics if too large
        $stats = $this->statistics->getGlobalStats();
        if ($stats['total_requests'] > 1000000) {
            $this->statistics->reset();
            $results['statistics_reset'] = true;
        }
        
        return $results;
    }
}