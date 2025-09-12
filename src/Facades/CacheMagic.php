<?php

namespace Fxcjahid\LaravelEloquentCacheMagic\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * Cache Magic Facade
 * 
 * @method static \Fxcjahid\LaravelEloquentCacheMagic\Monitoring\CacheStatistics statistics()
 * @method static \Fxcjahid\LaravelEloquentCacheMagic\Monitoring\CacheHealth health()
 * @method static bool clearTags(array $tags)
 * @method static bool clearModel(string $modelClass)
 * @method static bool clearAll()
 * @method static void warmModel(string $modelClass, array $queries = [])
 * @method static void warmQuery(string $modelClass, array $config)
 * @method static array status()
 * @method static bool isEnabled()
 * @method static void enable()
 * @method static void disable()
 * @method static mixed config(string $key = null, $default = null)
 * @method static void setConfig(string $key, $value)
 * @method static array driverInfo()
 * @method static mixed remember(string $key, $ttl, callable $callback, array $tags = [])
 * @method static bool forget(string $key, array $tags = [])
 * @method static int flushPattern(string $pattern)
 * @method static array size()
 * @method static array optimize()
 * 
 * @see \Fxcjahid\LaravelEloquentCacheMagic\CacheManager
 */
class CacheMagic extends Facade
{
    /**
     * Get the registered name of the component.
     *
     * @return string
     */
    protected static function getFacadeAccessor(): string
    {
        return 'cache-magic';
    }
}