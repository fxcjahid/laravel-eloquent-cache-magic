<?php

namespace Fxcjahid\LaravelEloquentCacheMagic\Monitoring;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\DB;

/**
 * Cache Health Monitoring
 * 
 * Monitor cache system health, performance, and potential issues.
 * 
 * @package Fxcjahid\LaravelEloquentCacheMagic\Monitoring
 */
class CacheHealth
{
    /**
     * Health status constants
     */
    const STATUS_HEALTHY = 'healthy';
    const STATUS_WARNING = 'warning';
    const STATUS_CRITICAL = 'critical';
    
    /**
     * Cache statistics instance
     */
    protected CacheStatistics $statistics;
    
    /**
     * Constructor
     */
    public function __construct()
    {
        $this->statistics = app(CacheStatistics::class);
    }
    
    /**
     * Perform health check
     */
    public function check(): array
    {
        $health = [
            'status' => self::STATUS_HEALTHY,
            'checks' => [],
            'metrics' => [],
            'recommendations' => [],
            'last_check' => now()->toIso8601String(),
        ];
        
        // Check cache driver availability
        $health['checks']['driver'] = $this->checkCacheDriver();
        
        // Check cache performance
        $health['checks']['performance'] = $this->checkPerformance();
        
        // Check hit rate
        $health['checks']['hit_rate'] = $this->checkHitRate();
        
        // Check memory usage (if Redis)
        if (config('cache.default') === 'redis') {
            $health['checks']['memory'] = $this->checkRedisMemory();
        }
        
        // Check connection health
        $health['checks']['connection'] = $this->checkConnection();
        
        // Calculate overall status
        $health['status'] = $this->calculateOverallStatus($health['checks']);
        
        // Get metrics
        $health['metrics'] = $this->getMetrics();
        
        // Generate recommendations
        $health['recommendations'] = $this->generateRecommendations($health);
        
        // Add summary
        $health['summary'] = $this->generateSummary($health);
        
        return $health;
    }
    
    /**
     * Check cache driver health
     */
    protected function checkCacheDriver(): array
    {
        $driver = config('cache.default');
        
        try {
            // Test write
            $testKey = 'health_check_' . uniqid();
            Cache::put($testKey, 'test', 10);
            
            // Test read
            $value = Cache::get($testKey);
            
            // Test delete
            Cache::forget($testKey);
            
            return [
                'status' => self::STATUS_HEALTHY,
                'driver' => $driver,
                'supports_tags' => Cache::supportsTags(),
                'message' => "Cache driver '{$driver}' is working correctly",
            ];
        } catch (\Exception $e) {
            return [
                'status' => self::STATUS_CRITICAL,
                'driver' => $driver,
                'supports_tags' => false,
                'message' => "Cache driver error: " . $e->getMessage(),
            ];
        }
    }
    
    /**
     * Check cache performance
     */
    protected function checkPerformance(): array
    {
        $iterations = 100;
        $startTime = microtime(true);
        
        // Test cache write/read performance
        for ($i = 0; $i < $iterations; $i++) {
            $key = 'perf_test_' . $i;
            Cache::put($key, str_repeat('x', 1024), 10); // 1KB data
            Cache::get($key);
            Cache::forget($key);
        }
        
        $elapsed = (microtime(true) - $startTime) * 1000; // Convert to ms
        $avgTime = $elapsed / $iterations;
        
        // Determine status based on average time
        if ($avgTime < 1) {
            $status = self::STATUS_HEALTHY;
            $message = 'Excellent performance';
        } elseif ($avgTime < 5) {
            $status = self::STATUS_HEALTHY;
            $message = 'Good performance';
        } elseif ($avgTime < 10) {
            $status = self::STATUS_WARNING;
            $message = 'Performance could be improved';
        } else {
            $status = self::STATUS_CRITICAL;
            $message = 'Poor performance detected';
        }
        
        return [
            'status' => $status,
            'avg_operation_time' => round($avgTime, 2) . 'ms',
            'total_time' => round($elapsed, 2) . 'ms',
            'operations' => $iterations * 3, // write + read + delete
            'message' => $message,
        ];
    }
    
    /**
     * Check cache hit rate
     */
    protected function checkHitRate(): array
    {
        $stats = $this->statistics->getGlobalStats();
        $hitRate = floatval($stats['hit_rate'] ?? 0);
        
        // Get configured thresholds
        $threshold = config('cache-magic.health.threshold.hit_rate', 0.5) * 100;
        
        if ($hitRate >= 90) {
            $status = self::STATUS_HEALTHY;
            $message = 'Excellent hit rate';
        } elseif ($hitRate >= 70) {
            $status = self::STATUS_HEALTHY;
            $message = 'Good hit rate';
        } elseif ($hitRate >= $threshold) {
            $status = self::STATUS_WARNING;
            $message = 'Hit rate below optimal level';
        } else {
            $status = self::STATUS_CRITICAL;
            $message = 'Hit rate critically low';
        }
        
        return [
            'status' => $status,
            'hit_rate' => $stats['hit_rate'],
            'hits' => $stats['hits'],
            'misses' => $stats['misses'],
            'threshold' => $threshold . '%',
            'message' => $message,
        ];
    }
    
    /**
     * Check Redis memory usage
     */
    protected function checkRedisMemory(): array
    {
        try {
            $info = Redis::info('memory');
            
            $usedMemory = $info['used_memory_human'] ?? 'N/A';
            $peakMemory = $info['used_memory_peak_human'] ?? 'N/A';
            $fragmentation = $info['mem_fragmentation_ratio'] ?? 1;
            
            // Check fragmentation ratio
            if ($fragmentation > 1.5) {
                $status = self::STATUS_WARNING;
                $message = 'High memory fragmentation detected';
            } elseif ($fragmentation < 1) {
                $status = self::STATUS_WARNING;
                $message = 'Memory swapping may be occurring';
            } else {
                $status = self::STATUS_HEALTHY;
                $message = 'Memory usage is healthy';
            }
            
            return [
                'status' => $status,
                'used_memory' => $usedMemory,
                'peak_memory' => $peakMemory,
                'fragmentation_ratio' => $fragmentation,
                'message' => $message,
            ];
        } catch (\Exception $e) {
            return [
                'status' => self::STATUS_WARNING,
                'message' => 'Unable to check Redis memory: ' . $e->getMessage(),
            ];
        }
    }
    
    /**
     * Check cache connection
     */
    protected function checkConnection(): array
    {
        $driver = config('cache.default');
        
        try {
            $startTime = microtime(true);
            
            switch ($driver) {
                case 'redis':
                    Redis::ping();
                    break;
                    
                case 'database':
                    DB::table('cache')->limit(1)->count();
                    break;
                    
                default:
                    Cache::get('connection_test');
            }
            
            $responseTime = (microtime(true) - $startTime) * 1000;
            
            if ($responseTime < 10) {
                $status = self::STATUS_HEALTHY;
                $message = 'Connection is fast';
            } elseif ($responseTime < 50) {
                $status = self::STATUS_WARNING;
                $message = 'Connection is slow';
            } else {
                $status = self::STATUS_CRITICAL;
                $message = 'Connection is very slow';
            }
            
            return [
                'status' => $status,
                'response_time' => round($responseTime, 2) . 'ms',
                'message' => $message,
            ];
        } catch (\Exception $e) {
            return [
                'status' => self::STATUS_CRITICAL,
                'message' => 'Connection failed: ' . $e->getMessage(),
            ];
        }
    }
    
    /**
     * Calculate overall health status
     */
    protected function calculateOverallStatus(array $checks): string
    {
        $statuses = array_column($checks, 'status');
        
        if (in_array(self::STATUS_CRITICAL, $statuses)) {
            return self::STATUS_CRITICAL;
        }
        
        if (in_array(self::STATUS_WARNING, $statuses)) {
            return self::STATUS_WARNING;
        }
        
        return self::STATUS_HEALTHY;
    }
    
    /**
     * Get performance metrics
     */
    protected function getMetrics(): array
    {
        $stats = $this->statistics->getGlobalStats();
        
        return [
            'total_operations' => $stats['total_requests'] ?? 0,
            'cache_size' => $this->getCacheSize(),
            'avg_response_time' => $this->getAverageResponseTime(),
            'memory_usage' => $this->getMemoryUsage(),
            'eviction_rate' => $this->getEvictionRate(),
        ];
    }
    
    /**
     * Generate recommendations based on health check
     */
    protected function generateRecommendations(array $health): array
    {
        $recommendations = [];
        
        // Check hit rate
        if (isset($health['checks']['hit_rate'])) {
            $hitRate = floatval($health['checks']['hit_rate']['hit_rate'] ?? 0);
            if ($hitRate < 70) {
                $recommendations[] = [
                    'type' => 'performance',
                    'priority' => 'high',
                    'message' => 'Consider increasing cache TTL for frequently accessed data',
                ];
                $recommendations[] = [
                    'type' => 'performance',
                    'priority' => 'medium',
                    'message' => 'Implement cache warming for critical queries',
                ];
            }
        }
        
        // Check driver
        if (!Cache::supportsTags()) {
            $recommendations[] = [
                'type' => 'configuration',
                'priority' => 'medium',
                'message' => 'Consider using Redis or Memcached for tag support',
            ];
        }
        
        // Check memory fragmentation
        if (isset($health['checks']['memory']['fragmentation_ratio'])) {
            $fragmentation = $health['checks']['memory']['fragmentation_ratio'];
            if ($fragmentation > 1.5) {
                $recommendations[] = [
                    'type' => 'maintenance',
                    'priority' => 'low',
                    'message' => 'Consider restarting Redis to reduce memory fragmentation',
                ];
            }
        }
        
        // Check performance
        if (isset($health['checks']['performance']['status'])) {
            if ($health['checks']['performance']['status'] === self::STATUS_WARNING) {
                $recommendations[] = [
                    'type' => 'infrastructure',
                    'priority' => 'medium',
                    'message' => 'Consider upgrading cache server resources',
                ];
            }
        }
        
        return $recommendations;
    }
    
    /**
     * Generate health summary
     */
    protected function generateSummary(array $health): string
    {
        $status = $health['status'];
        $driver = config('cache.default');
        
        $summaries = [
            self::STATUS_HEALTHY => "Cache system is healthy. Using {$driver} driver.",
            self::STATUS_WARNING => "Cache system has warnings. Review recommendations.",
            self::STATUS_CRITICAL => "Cache system has critical issues. Immediate action required.",
        ];
        
        return $summaries[$status] ?? 'Unknown status';
    }
    
    /**
     * Get cache size
     */
    protected function getCacheSize(): string
    {
        try {
            if (config('cache.default') === 'redis') {
                $info = Redis::info('memory');
                return $info['used_memory_human'] ?? 'N/A';
            }
            
            if (config('cache.default') === 'database') {
                $size = DB::table('cache')->count();
                return "{$size} entries";
            }
            
            return 'N/A';
        } catch (\Exception $e) {
            return 'Unable to determine';
        }
    }
    
    /**
     * Get average response time
     */
    protected function getAverageResponseTime(): string
    {
        // This would need to be tracked over time
        // For now, return a placeholder
        return 'N/A';
    }
    
    /**
     * Get memory usage
     */
    protected function getMemoryUsage(): string
    {
        return round(memory_get_usage(true) / 1024 / 1024, 2) . ' MB';
    }
    
    /**
     * Get eviction rate
     */
    protected function getEvictionRate(): string
    {
        try {
            if (config('cache.default') === 'redis') {
                $info = Redis::info('stats');
                $evicted = $info['evicted_keys'] ?? 0;
                return "{$evicted} keys";
            }
            
            return 'N/A';
        } catch (\Exception $e) {
            return 'N/A';
        }
    }
}