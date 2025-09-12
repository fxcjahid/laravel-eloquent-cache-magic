<?php

namespace Fxcjahid\LaravelEloquentCacheMagic\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Fxcjahid\LaravelEloquentCacheMagic\CacheQueryBuilder;

class CacheClearCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'cache-magic:clear 
                            {--tags=* : Clear specific cache tags}
                            {--key= : Clear specific cache key}
                            {--model= : Clear cache for specific model}
                            {--all : Clear all cache}
                            {--stats : Clear statistics only}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Clear Cache Magic cache entries';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle(): int
    {
        // Clear all cache
        if ($this->option('all')) {
            return $this->clearAll();
        }

        // Clear statistics only
        if ($this->option('stats')) {
            return $this->clearStatistics();
        }

        // Clear by tags
        if ($tags = $this->option('tags')) {
            return $this->clearByTags($tags);
        }

        // Clear by key
        if ($key = $this->option('key')) {
            return $this->clearByKey($key);
        }

        // Clear by model
        if ($model = $this->option('model')) {
            return $this->clearByModel($model);
        }

        // No options provided
        $this->warn('No options provided. Use --help to see available options.');
        return 1;
    }

    /**
     * Clear all cache
     */
    protected function clearAll(): int
    {
        $this->info('Clearing all cache...');
        
        try {
            Cache::flush();
            $this->info('✅ All cache cleared successfully!');
            return 0;
        } catch (\Exception $e) {
            $this->error('Failed to clear cache: ' . $e->getMessage());
            return 1;
        }
    }

    /**
     * Clear cache by tags
     */
    protected function clearByTags(array $tags): int
    {
        if (!Cache::supportsTags()) {
            $this->warn('Current cache driver does not support tags.');
            $this->info('Available drivers with tag support: Redis, Memcached');
            return 1;
        }

        $this->info('Clearing cache with tags: ' . implode(', ', $tags));
        
        try {
            Cache::tags($tags)->flush();
            $this->info('✅ Cache cleared for tags: ' . implode(', ', $tags));
            
            // Show statistics
            $this->table(
                ['Tag', 'Status'],
                array_map(fn($tag) => [$tag, '✅ Cleared'], $tags)
            );
            
            return 0;
        } catch (\Exception $e) {
            $this->error('Failed to clear cache by tags: ' . $e->getMessage());
            return 1;
        }
    }

    /**
     * Clear cache by key
     */
    protected function clearByKey(string $key): int
    {
        $this->info("Clearing cache key: {$key}");
        
        try {
            if (Cache::forget($key)) {
                $this->info("✅ Cache key '{$key}' cleared successfully!");
            } else {
                $this->warn("Cache key '{$key}' not found or already cleared.");
            }
            return 0;
        } catch (\Exception $e) {
            $this->error('Failed to clear cache key: ' . $e->getMessage());
            return 1;
        }
    }

    /**
     * Clear cache by model
     */
    protected function clearByModel(string $model): int
    {
        // Ensure model class exists
        if (!class_exists($model)) {
            // Try with App\Models prefix
            $model = "App\\Models\\{$model}";
            if (!class_exists($model)) {
                $this->error("Model class not found: {$model}");
                return 1;
            }
        }

        $modelName = class_basename($model);
        $tag = 'model:' . strtolower($modelName);
        
        $this->info("Clearing cache for model: {$modelName}");
        
        if (Cache::supportsTags()) {
            try {
                Cache::tags([$tag])->flush();
                $this->info("✅ Cache cleared for model: {$modelName}");
                
                // Clear related tags if model has them
                if (method_exists($model, 'getCacheTags')) {
                    $instance = new $model;
                    $additionalTags = $instance->getCacheTags();
                    if (!empty($additionalTags)) {
                        Cache::tags($additionalTags)->flush();
                        $this->info('Additional tags cleared: ' . implode(', ', $additionalTags));
                    }
                }
                
                return 0;
            } catch (\Exception $e) {
                $this->error('Failed to clear model cache: ' . $e->getMessage());
                return 1;
            }
        } else {
            $this->warn('Current cache driver does not support tags.');
            $this->info('Cannot clear model-specific cache without tag support.');
            return 1;
        }
    }

    /**
     * Clear statistics only
     */
    protected function clearStatistics(): int
    {
        $this->info('Clearing cache statistics...');
        
        try {
            $stats = app(\Fxcjahid\LaravelEloquentCacheMagic\Monitoring\CacheStatistics::class);
            $stats->reset();
            
            $this->info('✅ Cache statistics cleared successfully!');
            return 0;
        } catch (\Exception $e) {
            $this->error('Failed to clear statistics: ' . $e->getMessage());
            return 1;
        }
    }
}