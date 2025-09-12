<?php

namespace Fxcjahid\LaravelEloquentCacheMagic\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Fxcjahid\LaravelEloquentCacheMagic\Monitoring\CacheStatistics;
use Fxcjahid\LaravelEloquentCacheMagic\Monitoring\CacheHealth;

class CacheStatsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'cache-magic:stats 
                            {--export : Export statistics as JSON}
                            {--reset : Reset all statistics}
                            {--key= : Show statistics for specific cache key}
                            {--model= : Show statistics for specific model}
                            {--live : Show live statistics (refreshes every second)}
                            {--detailed : Show detailed statistics}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Display Cache Magic statistics and performance metrics';

    /**
     * Cache statistics instance
     */
    protected CacheStatistics $statistics;

    /**
     * Cache health instance
     */
    protected CacheHealth $health;

    /**
     * Create a new command instance.
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle(): int
    {
        $this->statistics = app(CacheStatistics::class);
        $this->health = app(CacheHealth::class);

        // Reset statistics
        if ($this->option('reset')) {
            return $this->resetStatistics();
        }

        // Export statistics
        if ($this->option('export')) {
            return $this->exportStatistics();
        }

        // Show key-specific statistics
        if ($key = $this->option('key')) {
            return $this->showKeyStatistics($key);
        }

        // Show model-specific statistics
        if ($model = $this->option('model')) {
            return $this->showModelStatistics($model);
        }

        // Show live statistics
        if ($this->option('live')) {
            return $this->showLiveStatistics();
        }

        // Show default statistics
        return $this->showStatistics();
    }

    /**
     * Show general statistics
     */
    protected function showStatistics(): int
    {
        $stats = $this->statistics->getGlobalStats();
        $health = $this->health->check();

        // Header
        $this->newLine();
        $this->info('╔═══════════════════════════════════════════════════════╗');
        $this->info('║          Cache Magic Statistics Dashboard            ║');
        $this->info('╚═══════════════════════════════════════════════════════╝');
        $this->newLine();

        // Cache Configuration
        $this->info('📋 Configuration');
        $this->table(
            ['Setting', 'Value'],
            [
                ['Cache Driver', config('cache.default')],
                ['Supports Tags', Cache::supportsTags() ? '✅ Yes' : '❌ No'],
                ['Statistics Enabled', config('cache-magic.statistics.enabled') ? '✅ Yes' : '❌ No'],
                ['Default TTL', config('cache-magic.default_ttl') . ' seconds'],
                ['Cache Version', config('cache-magic.version')],
            ]
        );

        // Performance Metrics
        $this->info('📊 Performance Metrics');
        $this->table(
            ['Metric', 'Value', 'Status'],
            [
                ['Total Requests', number_format($stats['total_requests']), $this->getStatusIcon($stats['total_requests'] > 0)],
                ['Cache Hits', number_format($stats['hits']), '🎯'],
                ['Cache Misses', number_format($stats['misses']), '❌'],
                ['Cache Writes', number_format($stats['writes']), '✍️'],
                ['Hit Rate', $stats['hit_rate'], $this->getHitRateStatus($stats['hit_rate'])],
                ['Miss Rate', $stats['miss_rate'], ''],
            ]
        );

        // Health Status
        $this->info('🏥 Health Status');
        $healthData = [
            ['Overall Health', $health['status'], $this->getHealthIcon($health['status'])],
            ['Response Time', $health['avg_response_time'] ?? 'N/A', $this->getResponseTimeStatus($health['avg_response_time'] ?? 0)],
            ['Memory Usage', $health['memory_usage'] ?? 'N/A', ''],
            ['Last Check', $health['last_check'] ?? 'N/A', ''],
        ];

        if ($this->option('detailed')) {
            $healthData[] = ['Cache Size', $health['cache_size'] ?? 'N/A', ''];
            $healthData[] = ['Eviction Rate', $health['eviction_rate'] ?? 'N/A', ''];
        }

        $this->table(['Metric', 'Value', 'Status'], $healthData);

        // Recommendations
        if ($hitRate = floatval($stats['hit_rate'])) {
            $this->showRecommendations($hitRate, $stats);
        }

        return 0;
    }

    /**
     * Show live statistics
     */
    protected function showLiveStatistics(): int
    {
        $this->info('📊 Live Cache Statistics (Press Ctrl+C to stop)');
        $this->newLine();

        while (true) {
            $stats = $this->statistics->getGlobalStats();
            
            // Clear screen (works on Unix-like systems)
            if (stripos(PHP_OS, 'WIN') === false) {
                system('clear');
            }

            $this->table(
                ['Metric', 'Value', 'Change'],
                [
                    ['Hit Rate', $stats['hit_rate'], ''],
                    ['Total Hits', number_format($stats['hits']), '+' . ($stats['in_memory']['hits'] ?? 0)],
                    ['Total Misses', number_format($stats['misses']), '+' . ($stats['in_memory']['misses'] ?? 0)],
                    ['Total Writes', number_format($stats['writes']), '+' . ($stats['in_memory']['writes'] ?? 0)],
                ]
            );

            sleep(1); // Refresh every second
        }

        return 0;
    }

    /**
     * Show key-specific statistics
     */
    protected function showKeyStatistics(string $key): int
    {
        if (!config('cache-magic.statistics.detailed', false)) {
            $this->error('Detailed statistics are not enabled.');
            $this->info('Enable it in config/cache-magic.php: statistics.detailed = true');
            return 1;
        }

        $stats = $this->statistics->getKeyStats($key);

        $this->info("📊 Statistics for key: {$key}");
        $this->table(
            ['Metric', 'Value'],
            [
                ['Cache Hits', number_format($stats['hits'] ?? 0)],
                ['Cache Misses', number_format($stats['misses'] ?? 0)],
                ['Cache Writes', number_format($stats['writes'] ?? 0)],
                ['Access Count', number_format($stats['access_count'] ?? 0)],
                ['Hit Rate', $stats['hit_rate'] ?? '0%'],
                ['Total Requests', number_format($stats['total_requests'] ?? 0)],
            ]
        );

        return 0;
    }

    /**
     * Show model-specific statistics
     */
    protected function showModelStatistics(string $model): int
    {
        if (!class_exists($model)) {
            $model = "App\\Models\\{$model}";
            if (!class_exists($model)) {
                $this->error("Model not found: {$model}");
                return 1;
            }
        }

        $stats = $this->statistics->getModelStats($model);
        $modelName = class_basename($model);

        $this->info("📊 Statistics for model: {$modelName}");
        $this->table(
            ['Metric', 'Value'],
            [
                ['Model Class', $model],
                ['Cache Tag', $stats['tag']],
                ['Global Hit Rate', $stats['global_stats']['hit_rate']],
                ['Total Requests', number_format($stats['global_stats']['total_requests'])],
            ]
        );

        return 0;
    }

    /**
     * Export statistics as JSON
     */
    protected function exportStatistics(): int
    {
        $data = $this->statistics->export();
        
        $json = json_encode($data, JSON_PRETTY_PRINT);
        
        // Output to stdout
        $this->line($json);
        
        // Optionally save to file
        if ($this->confirm('Save to file?')) {
            $filename = $this->ask('Enter filename', 'cache-stats-' . date('Y-m-d-His') . '.json');
            file_put_contents($filename, $json);
            $this->info("Statistics exported to: {$filename}");
        }

        return 0;
    }

    /**
     * Reset statistics
     */
    protected function resetStatistics(): int
    {
        if (!$this->confirm('Are you sure you want to reset all statistics?')) {
            $this->info('Operation cancelled.');
            return 0;
        }

        $this->statistics->reset();
        $this->info('✅ Statistics reset successfully!');
        
        return 0;
    }

    /**
     * Show recommendations based on statistics
     */
    protected function showRecommendations(float $hitRate, array $stats): void
    {
        $this->newLine();
        $this->info('💡 Recommendations');
        
        $recommendations = [];

        if ($hitRate < 50) {
            $recommendations[] = ['⚠️ Low Hit Rate', 'Consider increasing TTL or warming cache'];
        } elseif ($hitRate < 70) {
            $recommendations[] = ['📈 Moderate Hit Rate', 'Review frequently missed queries'];
        } else {
            $recommendations[] = ['✅ Good Hit Rate', 'Cache is performing well'];
        }

        if ($stats['misses'] > $stats['hits']) {
            $recommendations[] = ['🔍 High Miss Rate', 'Identify and optimize frequently missed queries'];
        }

        if (!Cache::supportsTags()) {
            $recommendations[] = ['🏷️ No Tag Support', 'Consider using Redis/Memcached for better invalidation'];
        }

        $this->table(['Finding', 'Recommendation'], $recommendations);
    }

    /**
     * Get status icon for hit rate
     */
    protected function getHitRateStatus(string $hitRate): string
    {
        $rate = floatval($hitRate);
        
        if ($rate >= 90) return '🟢 Excellent';
        if ($rate >= 70) return '🟡 Good';
        if ($rate >= 50) return '🟠 Fair';
        return '🔴 Poor';
    }

    /**
     * Get health icon
     */
    protected function getHealthIcon(string $status): string
    {
        return match(strtolower($status)) {
            'healthy' => '🟢',
            'warning' => '🟡',
            'critical' => '🔴',
            default => '⚪'
        };
    }

    /**
     * Get response time status
     */
    protected function getResponseTimeStatus($time): string
    {
        if (!is_numeric($time)) return '';
        
        if ($time < 10) return '🟢 Fast';
        if ($time < 50) return '🟡 Normal';
        if ($time < 100) return '🟠 Slow';
        return '🔴 Very Slow';
    }

    /**
     * Get general status icon
     */
    protected function getStatusIcon(bool $good): string
    {
        return $good ? '✅' : '⚠️';
    }
}