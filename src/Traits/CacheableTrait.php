<?php

namespace Fxcjahid\LaravelEloquentCacheMagic\Traits;

use Illuminate\Support\Facades\Cache;
use Fxcjahid\LaravelEloquentCacheMagic\CacheQueryBuilder;

/**
 * Cacheable Trait for Eloquent Models
 * 
 * Add this trait to any Eloquent model to enable automatic caching
 * and cache invalidation on model events.
 * 
 * @package Fxcjahid\LaravelEloquentCacheMagic\Traits
 */
trait CacheableTrait
{
    /**
     * Boot the cacheable trait
     */
    public static function bootCacheableTrait(): void
    {
        // Check if auto invalidation is enabled
        if (!config('cache-magic.auto_invalidation.enabled', true)) {
            return;
        }

        $events = config('cache-magic.auto_invalidation.events', []);

        // Register event listeners for cache invalidation
        if ($events['created'] ?? true) {
            static::created(function ($model) {
                $model->invalidateModelCache('created');
            });
        }

        if ($events['updated'] ?? true) {
            static::updated(function ($model) {
                $model->invalidateModelCache('updated');
            });
        }

        if ($events['deleted'] ?? true) {
            static::deleted(function ($model) {
                $model->invalidateModelCache('deleted');
            });
        }

        if ($events['restored'] ?? true) {
            static::restored(function ($model) {
                $model->invalidateModelCache('restored');
            });
        }

        if ($events['forceDeleted'] ?? true) {
            static::forceDeleted(function ($model) {
                $model->invalidateModelCache('forceDeleted');
            });
        }
    }

    /**
     * Invalidate model cache
     */
    public function invalidateModelCache(string $event = 'unknown'): void
    {
        // Get cache tags from model or use defaults
        $tags = $this->getCacheTags();

        // Add model-specific tags
        $tags[] = $this->getCacheTagForModel();
        $tags[] = $this->getCacheTagForInstance();

        // Clear cache by tags if supported
        if (Cache::supportsTags() && !empty($tags)) {
            Cache::tags($tags)->flush();
            
            $this->logCacheInvalidation($event, $tags);
        } else {
            // Fallback: Clear specific cache keys if no tag support
            $this->clearNonTaggedCache();
        }

        // Fire custom invalidation event if defined
        if (method_exists($this, 'onCacheInvalidated')) {
            $this->onCacheInvalidated($event);
        }
    }

    /**
     * Get cache tags for this model
     */
    public function getCacheTags(): array
    {
        $tags = property_exists($this, 'cacheTags') 
            ? $this->cacheTags 
            : [];

        // Add dynamic tags
        if (method_exists($this, 'dynamicCacheTags')) {
            $tags = array_merge($tags, $this->dynamicCacheTags());
        }

        return array_unique($tags);
    }

    /**
     * Get cache tag for this model class
     */
    public function getCacheTagForModel(): string
    {
        return 'model:' . strtolower(class_basename($this));
    }

    /**
     * Get cache tag for this specific instance
     */
    public function getCacheTagForInstance(): string
    {
        return $this->getCacheTagForModel() . ':' . $this->getKey();
    }

    /**
     * Get cache expiry time
     */
    public function getCacheExpiry(): int
    {
        return property_exists($this, 'cacheExpiry') 
            ? $this->cacheExpiry 
            : config('cache-magic.default_ttl', 3600);
    }

    /**
     * Clear non-tagged cache (fallback for file/database drivers)
     */
    protected function clearNonTaggedCache(): void
    {
        // This is a fallback method when tags are not supported
        // You can implement custom logic here based on your needs
        
        // Example: Clear cache keys with model prefix
        $prefix = $this->getCacheTagForModel();
        
        // Note: This is limited as we can't enumerate all keys in some drivers
        // Consider maintaining a registry of cache keys if needed
    }

    /**
     * Log cache invalidation
     */
    protected function logCacheInvalidation(string $event, array $tags): void
    {
        if (config('cache-magic.debug', false)) {
            logger()->debug('Model cache invalidated', [
                'model' => get_class($this),
                'event' => $event,
                'id' => $this->getKey(),
                'tags' => $tags,
            ]);
        }
    }

    /**
     * Cache a query scope
     */
    public function scopeCache($query, $ttl = null, array $tags = []): CacheQueryBuilder
    {
        $tags = array_merge($this->getCacheTags(), $tags);
        
        return new CacheQueryBuilder($query, [
            'ttl' => $ttl ?? $this->getCacheExpiry(),
            'tags' => $tags,
        ]);
    }

    /**
     * Cache and get the model by ID
     */
    public static function findCached($id, $ttl = null): ?static
    {
        $instance = new static;
        $tags = $instance->getCacheTags();
        $tags[] = $instance->getCacheTagForModel();
        $tags[] = $instance->getCacheTagForModel() . ':' . $id;

        return static::where((new static)->getKeyName(), $id)
            ->cache([
                'ttl' => $ttl ?? $instance->getCacheExpiry(),
                'tags' => $tags,
                'key' => $instance->getCacheTagForModel() . ':find:' . $id,
            ])
            ->first();
    }

    /**
     * Cache and get all models
     */
    public static function allCached($ttl = null)
    {
        $instance = new static;
        $tags = $instance->getCacheTags();
        $tags[] = $instance->getCacheTagForModel();

        return static::cache([
            'ttl' => $ttl ?? $instance->getCacheExpiry(),
            'tags' => $tags,
            'key' => $instance->getCacheTagForModel() . ':all',
        ])->get();
    }

    /**
     * Remember a value in cache
     */
    public function remember(string $key, $ttl, callable $callback)
    {
        $tags = $this->getCacheTags();
        $tags[] = $this->getCacheTagForInstance();
        
        $cacheKey = $this->getCacheTagForInstance() . ':' . $key;

        if (Cache::supportsTags()) {
            return Cache::tags($tags)->remember($cacheKey, $ttl ?? $this->getCacheExpiry(), $callback);
        }

        return Cache::remember($cacheKey, $ttl ?? $this->getCacheExpiry(), $callback);
    }

    /**
     * Forget a cached value
     */
    public function forget(string $key): bool
    {
        $tags = $this->getCacheTags();
        $tags[] = $this->getCacheTagForInstance();
        
        $cacheKey = $this->getCacheTagForInstance() . ':' . $key;

        if (Cache::supportsTags()) {
            return Cache::tags($tags)->forget($cacheKey);
        }

        return Cache::forget($cacheKey);
    }

    /**
     * Warm up cache for this model
     */
    public function warmUpCache(): void
    {
        // Find by ID
        static::findCached($this->getKey());

        // Warm up relationships if defined
        if (method_exists($this, 'cacheableRelations')) {
            foreach ($this->cacheableRelations() as $relation) {
                $this->load($relation);
            }
        }

        // Call custom warm up method if defined
        if (method_exists($this, 'customWarmUp')) {
            $this->customWarmUp();
        }
    }

    /**
     * Get cache statistics for this model
     */
    public function getCacheStatistics(): array
    {
        $stats = app(\Fxcjahid\LaravelEloquentCacheMagic\Monitoring\CacheStatistics::class);
        
        return [
            'model' => get_class($this),
            'cache_tag' => $this->getCacheTagForModel(),
            'instance_tag' => $this->getCacheTagForInstance(),
            'ttl' => $this->getCacheExpiry(),
            'supports_tags' => Cache::supportsTags(),
            'stats' => $stats->getModelStats(get_class($this)),
        ];
    }
}