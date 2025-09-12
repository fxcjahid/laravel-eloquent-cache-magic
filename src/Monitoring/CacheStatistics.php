<?php

namespace Fxcjahid\LaravelEloquentCacheMagic\Monitoring;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Collection;

/**
 * Cache Statistics Monitoring
 * 
 * Track and analyze cache performance metrics including hit rates,
 * miss rates, and access patterns.
 * 
 * @package Fxcjahid\LaravelEloquentCacheMagic\Monitoring
 */
class CacheStatistics
{
    /**
     * Statistics storage key prefix
     */
    protected const STATS_PREFIX = 'cache_magic:stats:';

    /**
     * In-memory statistics cache
     */
    protected array $stats = [
        'hits' => 0,
        'misses' => 0,
        'writes' => 0,
    ];

    /**
     * Record a cache hit
     */
    public function recordHit(string $key): void
    {
        $this->incrementStat('hits');
        $this->incrementKeyStat($key, 'hits');
        $this->recordAccess($key);
    }

    /**
     * Record a cache miss
     */
    public function recordMiss(string $key): void
    {
        $this->incrementStat('misses');
        $this->incrementKeyStat($key, 'misses');
    }

    /**
     * Record a cache write
     */
    public function recordWrite(string $key): void
    {
        $this->incrementStat('writes');
        $this->incrementKeyStat($key, 'writes');
    }

    /**
     * Record cache access for adaptive TTL
     */
    public function recordAccess(string $key): void
    {
        if (!config('cache-magic.adaptive_ttl.enabled', false)) {
            return;
        }

        $accessKey = self::STATS_PREFIX . 'access:' . md5($key);
        $count = Cache::increment($accessKey);
        
        // Set expiry for access count
        if ($count === 1) {
            Cache::put($accessKey, 1, config('cache-magic.statistics.ttl', 86400));
        }
    }

    /**
     * Get access count for a cache key
     */
    public function getAccessCount(string $key): int
    {
        $accessKey = self::STATS_PREFIX . 'access:' . md5($key);
        return (int) Cache::get($accessKey, 0);
    }

    /**
     * Increment a statistic
     */
    protected function incrementStat(string $stat): void
    {
        if (!config('cache-magic.statistics.enabled', true)) {
            return;
        }

        $this->stats[$stat]++;
        
        $key = self::STATS_PREFIX . 'global:' . $stat;
        Cache::increment($key);
    }

    /**
     * Increment a key-specific statistic
     */
    protected function incrementKeyStat(string $key, string $stat): void
    {
        if (!config('cache-magic.statistics.detailed', false)) {
            return;
        }

        $statKey = self::STATS_PREFIX . 'key:' . md5($key) . ':' . $stat;
        Cache::increment($statKey);
    }

    /**
     * Get global statistics
     */
    public function getGlobalStats(): array
    {
        $hits = (int) Cache::get(self::STATS_PREFIX . 'global:hits', 0);
        $misses = (int) Cache::get(self::STATS_PREFIX . 'global:misses', 0);
        $writes = (int) Cache::get(self::STATS_PREFIX . 'global:writes', 0);
        
        $total = $hits + $misses;
        $hitRate = $total > 0 ? round(($hits / $total) * 100, 2) : 0;

        return [
            'hits' => $hits,
            'misses' => $misses,
            'writes' => $writes,
            'total_requests' => $total,
            'hit_rate' => $hitRate . '%',
            'miss_rate' => $total > 0 ? (100 - $hitRate) . '%' : '0%',
            'in_memory' => $this->stats,
        ];
    }

    /**
     * Get statistics for a specific key
     */
    public function getKeyStats(string $key): array
    {
        if (!config('cache-magic.statistics.detailed', false)) {
            return ['error' => 'Detailed statistics not enabled'];
        }

        $keyHash = md5($key);
        $hits = (int) Cache::get(self::STATS_PREFIX . 'key:' . $keyHash . ':hits', 0);
        $misses = (int) Cache::get(self::STATS_PREFIX . 'key:' . $keyHash . ':misses', 0);
        $writes = (int) Cache::get(self::STATS_PREFIX . 'key:' . $keyHash . ':writes', 0);
        $accessCount = $this->getAccessCount($key);

        $total = $hits + $misses;
        $hitRate = $total > 0 ? round(($hits / $total) * 100, 2) : 0;

        return [
            'key' => $key,
            'hits' => $hits,
            'misses' => $misses,
            'writes' => $writes,
            'access_count' => $accessCount,
            'total_requests' => $total,
            'hit_rate' => $hitRate . '%',
        ];
    }

    /**
     * Get statistics for a model
     */
    public function getModelStats(string $modelClass): array
    {
        $modelTag = 'model:' . strtolower(class_basename($modelClass));
        
        // This is a simplified version
        // In production, you might want to track model-specific stats
        return [
            'model' => $modelClass,
            'tag' => $modelTag,
            'global_stats' => $this->getGlobalStats(),
        ];
    }

    /**
     * Get top accessed keys
     */
    public function getTopKeys(int $limit = 10): Collection
    {
        if (!config('cache-magic.statistics.detailed', false)) {
            return collect(['error' => 'Detailed statistics not enabled']);
        }

        // This would require maintaining a sorted set in Redis
        // For now, return a placeholder
        return collect([
            'info' => 'Top keys tracking requires Redis sorted sets',
            'limit' => $limit,
        ]);
    }

    /**
     * Reset all statistics
     */
    public function reset(): void
    {
        // Reset in-memory stats
        $this->stats = [
            'hits' => 0,
            'misses' => 0,
            'writes' => 0,
        ];

        // Clear all statistics from cache
        Cache::deleteMultiple([
            self::STATS_PREFIX . 'global:hits',
            self::STATS_PREFIX . 'global:misses',
            self::STATS_PREFIX . 'global:writes',
        ]);

        // Note: Detailed per-key stats would need additional cleanup
    }

    /**
     * Export statistics
     */
    public function export(): array
    {
        return [
            'timestamp' => now()->toIso8601String(),
            'global' => $this->getGlobalStats(),
            'cache_driver' => config('cache.default'),
            'supports_tags' => Cache::supportsTags(),
            'config' => [
                'enabled' => config('cache-magic.enabled'),
                'default_ttl' => config('cache-magic.default_ttl'),
                'adaptive_ttl' => config('cache-magic.adaptive_ttl.enabled'),
                'auto_invalidation' => config('cache-magic.auto_invalidation.enabled'),
            ],
        ];
    }

    /**
     * Generate a report
     */
    public function generateReport(): string
    {
        $stats = $this->getGlobalStats();
        
        $report = "Cache Magic Statistics Report\n";
        $report .= "==============================\n\n";
        $report .= "Generated: " . now()->format('Y-m-d H:i:s') . "\n";
        $report .= "Cache Driver: " . config('cache.default') . "\n";
        $report .= "Tags Support: " . (Cache::supportsTags() ? 'Yes' : 'No') . "\n\n";
        
        $report .= "Performance Metrics:\n";
        $report .= "-------------------\n";
        $report .= "Total Requests: " . $stats['total_requests'] . "\n";
        $report .= "Cache Hits: " . $stats['hits'] . "\n";
        $report .= "Cache Misses: " . $stats['misses'] . "\n";
        $report .= "Cache Writes: " . $stats['writes'] . "\n";
        $report .= "Hit Rate: " . $stats['hit_rate'] . "\n";
        $report .= "Miss Rate: " . $stats['miss_rate'] . "\n";
        
        return $report;
    }
}