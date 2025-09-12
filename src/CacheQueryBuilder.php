<?php

namespace Fxcjahid\LaravelEloquentCacheMagic;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Fxcjahid\LaravelEloquentCacheMagic\Monitoring\CacheStatistics;
use Fxcjahid\LaravelEloquentCacheMagic\Jobs\RefreshCacheJob;
use Fxcjahid\LaravelEloquentCacheMagic\Events\CacheHit;
use Fxcjahid\LaravelEloquentCacheMagic\Events\CacheMiss;
use Fxcjahid\LaravelEloquentCacheMagic\Events\CacheWrite;
use Fxcjahid\LaravelEloquentCacheMagic\Exceptions\CacheException;

/**
 * Enhanced Cache Query Builder for Laravel Eloquent
 * 
 * A powerful caching wrapper for Laravel Eloquent queries with advanced features:
 * - Automatic cache invalidation on model events
 * - Redis/Memcached tag support with fallback
 * - Cache statistics and monitoring
 * - Adaptive TTL based on usage
 * - Async cache refresh
 * - Debug mode with detailed logging
 * 
 * @package Fxcjahid\LaravelEloquentCacheMagic
 * @author FXC Jahid <fxcjahid@gmail.com>
 * @version 2.0.0
 */
class CacheQueryBuilder
{
    /**
     * The query builder instance
     */
    protected $query;

    /**
     * Cache time-to-live in seconds
     */
    protected int $ttl;

    /**
     * Cache tags for invalidation
     */
    protected array $tags = [];

    /**
     * Custom cache key
     */
    protected ?string $cacheKey = null;

    /**
     * Force refresh cache
     */
    protected bool $forceRefresh = false;

    /**
     * Debug mode flag
     */
    protected bool $debugMode = false;

    /**
     * Cache version for easy invalidation
     */
    protected ?string $version = null;

    /**
     * Use adaptive TTL based on access frequency
     */
    protected bool $adaptiveTtl = false;

    /**
     * Cache statistics instance
     */
    protected CacheStatistics $statistics;

    /**
     * Flag to disable caching for this query
     */
    protected bool $cacheDisabled = false;

    /**
     * Constructor
     */
    public function __construct($query = null, array $options = [])
    {
        $this->query      = $query;
        $this->statistics = app(CacheStatistics::class);

        $modelConfig = $this->getModelCacheConfig();

        $this->ttl          = $options['ttl'] ?? $modelConfig['ttl'] ?? config('cache-magic.default_ttl', 3600);
        $this->tags         = $options['tags'] ?? $modelConfig['tags'] ?? [];
        $this->cacheKey     = $options['key'] ?? null;
        $this->forceRefresh = $options['refresh'] ?? false;
        $this->debugMode    = $options['debug'] ?? config('cache-magic.debug', false);
        $this->version      = $options['version'] ?? config('cache-magic.version', '1');
        $this->adaptiveTtl  = $options['adaptive'] ?? config('cache-magic.adaptive_ttl.enabled', false);

        // Add global tags if configured
        if ($globalTags = config('cache-magic.global_tags', [])) {
            $this->tags = array_merge($this->tags, $globalTags);
        }

        // Automatically add user/guest tag
        $this->addUserTag();
    }

    /**
     * Automatically add user or guest tag to cache
     */
    protected function addUserTag(): void
    {
        // Check if auto user tags are enabled
        if (!config('cache-magic.auto_user_tags.enabled', true)) {
            return;
        }

        if (Auth::check()) {
            // For authenticated users
            $this->tags[] = 'user:' . Auth::id();
        } else {
            // For guests, use configured fallback strategy
            $fallback = config('cache-magic.auto_user_tags.guest_fallback', 'session');
            $guestId = $this->getGuestIdentifier($fallback);
            $this->tags[] = 'guest:' . $guestId;
        }
    }

    /**
     * Get guest identifier based on fallback strategy
     */
    protected function getGuestIdentifier(string $fallback): string
    {
        switch ($fallback) {
            case 'session':
                // Use session ID if available
                return session()->getId() ?: uniqid('no-session-');
                
            case 'ip':
                // Use IP address (be careful with this in production)
                return md5(request()->ip() ?: 'no-ip');
                
            case 'unique':
            default:
                // Always generate a unique ID (no cache sharing between requests)
                return uniqid('guest-');
        }
    }

    /**
     * Get cache configuration from model
     */
    protected function getModelCacheConfig(): array
    {
        if ($this->query instanceof \Illuminate\Database\Eloquent\Builder) {
            $model = $this->query->getModel();

            return [
                'ttl'  => $this->getModelProperty($model, 'cacheExpiry', config('cache-magic.default_ttl', 3600)),
                'tags' => $this->getModelProperty($model, 'cacheTags', []),
            ];
        }

        return ['ttl' => config('cache-magic.default_ttl', 3600), 'tags' => []];
    }

    /**
     * Get property from model with fallback
     */
    protected function getModelProperty($model, string $property, $default = null)
    {
        if (!$model || !is_object($model)) {
            return $default;
        }
        
        return property_exists($model, $property) ? $model->$property : $default;
    }

    /**
     * Set cache TTL
     */
    public function ttl(int $seconds): self
    {
        $this->ttl = $seconds;
        return $this;
    }

    /**
     * Set cache tags
     */
    public function tags(array $tags): self
    {
        $this->tags = array_merge($this->tags, $tags);
        return $this;
    }

    /**
     * Set custom cache key
     */
    public function key(string $key): self
    {
        $this->cacheKey = $key;
        return $this;
    }

    /**
     * Force refresh cache
     */
    public function refresh(bool $refresh = true): self
    {
        $this->forceRefresh = $refresh;
        return $this;
    }

    /**
     * Enable debug mode
     */
    public function debug(bool $enabled = true): self
    {
        $this->debugMode = $enabled;
        return $this;
    }

    /**
     * Set cache version
     */
    public function version(string $version): self
    {
        $this->version = $version;
        return $this;
    }

    /**
     * Enable adaptive TTL
     */
    public function adaptive(bool $enabled = true): self
    {
        $this->adaptiveTtl = $enabled;
        return $this;
    }

    /**
     * Execute query with caching
     */
    public function get($columns = ['*'])
    {
        $cacheKey = $this->resolveCacheKey($columns);
        $ttl      = $this->resolveTtl($cacheKey);

        return $this->executeWithCache($cacheKey, $ttl, function () use ($columns) {
            return $this->query->get($columns);
        });
    }

    /**
     * Execute first() with caching
     */
    public function first($columns = ['*'])
    {
        $cacheKey = $this->resolveCacheKey($columns, 'first');
        $ttl      = $this->resolveTtl($cacheKey);

        return $this->executeWithCache($cacheKey, $ttl, function () use ($columns) {
            return $this->query->first($columns);
        });
    }

    /**
     * Execute count() with caching
     */
    public function count($columns = '*')
    {
        $cacheKey = $this->resolveCacheKey([$columns], 'count');
        $ttl      = $this->resolveTtl($cacheKey);

        return $this->executeWithCache($cacheKey, $ttl, function () use ($columns) {
            return $this->query->count($columns);
        });
    }

    /**
     * Execute sum() with caching
     */
    public function sum($column)
    {
        $cacheKey = $this->resolveCacheKey([$column], 'sum');
        $ttl      = $this->resolveTtl($cacheKey);

        return $this->executeWithCache($cacheKey, $ttl, function () use ($column) {
            return $this->query->sum($column);
        });
    }

    /**
     * Execute avg() with caching
     */
    public function avg($column)
    {
        $cacheKey = $this->resolveCacheKey([$column], 'avg');
        $ttl      = $this->resolveTtl($cacheKey);

        return $this->executeWithCache($cacheKey, $ttl, function () use ($column) {
            return $this->query->avg($column);
        });
    }

    /**
     * Execute max() with caching
     */
    public function max($column)
    {
        $cacheKey = $this->resolveCacheKey([$column], 'max');
        $ttl      = $this->resolveTtl($cacheKey);

        return $this->executeWithCache($cacheKey, $ttl, function () use ($column) {
            return $this->query->max($column);
        });
    }

    /**
     * Execute min() with caching
     */
    public function min($column)
    {
        $cacheKey = $this->resolveCacheKey([$column], 'min');
        $ttl      = $this->resolveTtl($cacheKey);

        return $this->executeWithCache($cacheKey, $ttl, function () use ($column) {
            return $this->query->min($column);
        });
    }

    /**
     * Disable caching for this query and return the original query builder
     * 
     * @return mixed
     */
    public function doNotCache()
    {
        $this->cacheDisabled = true;
        return $this->query;
    }
    
    /**
     * Get current tags
     * 
     * @return array
     */
    public function getTags(): array
    {
        return $this->tags;
    }

    /**
     * Execute exists() with caching
     */
    public function exists(): bool
    {
        $cacheKey = $this->resolveCacheKey([], 'exists');
        $ttl      = $this->resolveTtl($cacheKey);

        return $this->executeWithCache($cacheKey, $ttl, function () {
            return $this->query->exists();
        });
    }

    /**
     * Execute pluck() with caching
     */
    public function pluck($column, $key = null)
    {
        $cacheKey = $this->resolveCacheKey([$column, $key], 'pluck');
        $ttl      = $this->resolveTtl($cacheKey);

        return $this->executeWithCache($cacheKey, $ttl, function () use ($column, $key) {
            return $this->query->pluck($column, $key);
        });
    }

    /**
     * Execute the query with cache logic
     */
    protected function executeWithCache(string $cacheKey, int $ttl, callable $callback)
    {
        // If caching is disabled, execute the callback directly
        if ($this->cacheDisabled) {
            return $callback();
        }

        // Log the operation if debug mode is enabled
        $this->logOperation('attempt', $cacheKey);

        // Add adaptive TTL logic
        if ($this->adaptiveTtl) {
            $ttl = $this->calculateAdaptiveTtl($cacheKey, $ttl);
        }

        // Check if we should use tagged cache
        if ($this->shouldUseTags()) {
            return $this->executeWithTaggedCache($cacheKey, $ttl, $callback);
        }

        return $this->executeWithDefaultCache($cacheKey, $ttl, $callback);
    }

    /**
     * Execute with tagged cache (Redis/Memcached)
     */
    protected function executeWithTaggedCache(string $cacheKey, int $ttl, callable $callback)
    {
        if ($this->forceRefresh) {
            Cache::tags($this->tags)->forget($cacheKey);
            $this->logOperation('refresh', $cacheKey);
        }

        $result = Cache::tags($this->tags)->remember($cacheKey, $ttl, function () use ($callback, $cacheKey) {
            $this->recordMiss($cacheKey);
            $data = $callback();
            $this->recordWrite($cacheKey);
            return $data;
        });

        // Check if it was a cache hit (not freshly written)
        if (! $this->forceRefresh) {
            $this->recordHit($cacheKey);
        }

        return $result;
    }

    /**
     * Execute with default cache (File/Database)
     */
    protected function executeWithDefaultCache(string $cacheKey, int $ttl, callable $callback)
    {
        if ($this->forceRefresh) {
            Cache::forget($cacheKey);
            $this->logOperation('refresh', $cacheKey);
        }

        $result = Cache::remember($cacheKey, $ttl, function () use ($callback, $cacheKey) {
            $this->recordMiss($cacheKey);
            $data = $callback();
            $this->recordWrite($cacheKey);
            return $data;
        });

        // Check if it was a cache hit
        if (! $this->forceRefresh) {
            $this->recordHit($cacheKey);
        }

        return $result;
    }

    /**
     * Refresh cache asynchronously
     */
    public function refreshAsync(): self
    {
        dispatch(new RefreshCacheJob($this->query, [
            'ttl'  => $this->ttl,
            'tags' => $this->tags,
            'key'  => $this->cacheKey,
        ]));

        $this->logOperation('async_refresh', $this->cacheKey ?? 'auto');

        return $this;
    }

    /**
     * Clear cache for this query
     */
    public function clearCache(): bool
    {
        $cacheKey = $this->cacheKey ?? $this->generateCacheKey();

        if ($this->shouldUseTags()) {
            Cache::tags($this->tags)->flush();
            $this->logOperation('clear_tags', implode(',', $this->tags));
            return true;
        }

        if ($cacheKey) {
            Cache::forget($cacheKey);
            $this->logOperation('clear_key', $cacheKey);
            return true;
        }

        return false;
    }

    /**
     * Clear cache by tags
     */
    public static function clearByTags(array $tags): bool
    {
        if (Cache::supportsTags()) {
            Cache::tags($tags)->flush();
            Log::info('Cache cleared by tags', ['tags' => $tags]);
            return true;
        }

        return false;
    }

    /**
     * Warm up cache
     */
    public function warmUp(): void
    {
        $this->forceRefresh = true;
        $this->get();
        $this->logOperation('warm_up', $this->cacheKey ?? 'auto');
    }

    /**
     * Check if we should use tagged cache
     */
    protected function shouldUseTags(): bool
    {
        return Cache::supportsTags() && ! empty($this->tags);
    }

    /**
     * Resolve cache key
     */
    protected function resolveCacheKey($columns = ['*'], string $method = 'get'): string
    {
        return $this->cacheKey ?? $this->generateCacheKey($columns, $method);
    }

    /**
     * Generate cache key
     */
    protected function generateCacheKey($columns = ['*'], string $method = 'get'): string
    {
        $sql      = $this->query->toSql();
        $bindings = $this->query->getBindings();
        $user     = Auth::user();

        $keyParts = [
            'v'.$this->version,
            $method,
            md5($sql.serialize($bindings).serialize($columns)),
            $user ? 'u'.$user->id : 'guest'
        ];

        return implode(':', $keyParts);
    }

    /**
     * Resolve TTL with adaptive logic
     */
    protected function resolveTtl(string $cacheKey): int
    {
        if (! $this->adaptiveTtl) {
            return $this->ttl;
        }

        $accessCount = $this->statistics->getAccessCount($cacheKey);

        // Adaptive TTL based on access frequency
        if ($accessCount > 100) {
            return min($this->ttl * 2, 86400); // Max 24 hours
        }

        if ($accessCount > 50) {
            return $this->ttl;
        }

        return max($this->ttl / 2, 300); // Min 5 minutes
    }

    /**
     * Record cache hit
     */
    protected function recordHit(string $key): void
    {
        $this->statistics->recordHit($key);
        event(new CacheHit($key, $this->tags));
        $this->logOperation('hit', $key);
    }

    /**
     * Record cache miss
     */
    protected function recordMiss(string $key): void
    {
        $this->statistics->recordMiss($key);
        event(new CacheMiss($key, $this->tags));
        $this->logOperation('miss', $key);
    }

    /**
     * Record cache write
     */
    protected function recordWrite(string $key): void
    {
        $this->statistics->recordWrite($key);
        event(new CacheWrite($key, $this->tags, $this->ttl));
        $this->logOperation('write', $key);
    }

    /**
     * Log cache operation
     */
    protected function logOperation(string $operation, string $key): void
    {
        if (! $this->debugMode) {
            return;
        }

        Log::channel(config('cache-magic.log_channel', 'single'))->debug('Cache operation', [
            'operation'     => $operation,
            'key'           => $key,
            'tags'          => $this->tags,
            'ttl'           => $this->ttl,
            'driver'        => config('cache.default'),
            'supports_tags' => Cache::supportsTags(),
        ]);
    }

    /**
     * Handle dynamic method calls
     */
    public function __call($method, $parameters)
    {
        if (is_callable($this->query)) {
            throw new CacheException("Method {$method} does not exist on cached callback. Use execute() instead.");
        }

        $result = $this->query->$method(...$parameters);

        // If result is still a query builder, return new cached instance
        if ($result instanceof \Illuminate\Database\Eloquent\Builder ||
            $result instanceof \Illuminate\Database\Query\Builder) {
            return new static($result, [
                'ttl'      => $this->ttl,
                'tags'     => $this->tags,
                'key'      => $this->cacheKey,
                'refresh'  => $this->forceRefresh,
                'debug'    => $this->debugMode,
                'version'  => $this->version,
                'adaptive' => $this->adaptiveTtl,
            ]);
        }

        return $result;
    }

    /**
     * Static helper to create instance for callbacks
     */
    public static function callback(callable $callback, array $options = []): mixed
    {
        $instance = new static($callback, $options);
        return $instance->executeCallback();
    }

    /**
     * Execute callback with caching
     */
    protected function executeCallback()
    {
        $cacheKey = $this->cacheKey ?? $this->generateCallbackCacheKey();
        $ttl      = $this->resolveTtl($cacheKey);

        return $this->executeWithCache($cacheKey, $ttl, $this->query);
    }

    /**
     * Generate cache key for callbacks
     */
    protected function generateCallbackCacheKey(): string
    {
        $user = Auth::user();
        return 'callback:v'.$this->version.':'.md5(serialize($this->tags).($user ? $user->id : 'guest').microtime());
    }
}