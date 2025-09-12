# Laravel Eloquent Cache Magic - Complete Documentation

## Table of Contents
1. [Installation](#installation)
2. [Configuration](#configuration)
3. [Basic Usage](#basic-usage)
4. [Query Methods](#query-methods)
5. [Cache Tags Explained](#cache-tags-explained)
6. [Model Integration](#model-integration)
7. [Console Commands](#console-commands)
8. [API Middleware](#api-middleware)
9. [Statistics & Monitoring](#statistics--monitoring)
10. [Helper Functions](#helper-functions)
11. [Advanced Features](#advanced-features)
12. [Troubleshooting](#troubleshooting)

---

## Installation

### Requirements
- PHP 8.0, 8.1, 8.2, 8.3, or 8.4
- Laravel 10.0, 11.0, or 12.0
- Redis or Memcached (optional, for tag support)

### Step 1: Install Package
```bash
composer require fxcjahid/laravel-eloquent-cache-magic
```

### Step 2: Publish Configuration (Optional)
```bash
php artisan vendor:publish --tag=cache-magic-config
```

### Step 3: Configure Cache Driver

#### For Redis (Recommended)
```bash
composer require predis/predis
```

`.env` file:
```env
CACHE_DRIVER=redis
REDIS_HOST=127.0.0.1
REDIS_PASSWORD=null
REDIS_PORT=6379
```

#### For Memcached
```bash
pecl install memcached
```

`.env` file:
```env
CACHE_DRIVER=memcached
MEMCACHED_HOST=127.0.0.1
MEMCACHED_PORT=11211
```

---

## Configuration

All configuration options are in `config/cache-magic.php`:

```php
return [
    // Master switch to enable/disable caching
    'enabled' => env('CACHE_MAGIC_ENABLED', true),
    
    // Default cache duration in seconds (1 hour)
    'default_ttl' => env('CACHE_MAGIC_DEFAULT_TTL', 3600),
    
    // Cache version for easy invalidation
    'version' => env('CACHE_MAGIC_VERSION', '1'),
    
    // Global tags applied to all cache entries
    'global_tags' => ['app'],
    
    // Automatic cache invalidation on model events
    'auto_invalidation' => [
        'enabled' => true,
        'events' => [
            'created' => true,
            'updated' => true,
            'deleted' => true,
            'restored' => true,
            'forceDeleted' => true,
        ],
    ],
    
    // Adaptive TTL based on access frequency
    'adaptive_ttl' => [
        'enabled' => false,
        'min_ttl' => 300,     // 5 minutes
        'max_ttl' => 86400,   // 24 hours
        'thresholds' => [
            'hot' => 100,     // Access count for hot data
            'warm' => 50,     // Access count for warm data
        ],
    ],
    
    // Auto User Tags - Automatically add user/guest tags
    'auto_user_tags' => [
        'enabled' => true,
        'guest_fallback' => 'session', // Options: 'session', 'ip', 'unique'
    ],
            'hot' => 100,     // Access count for hot data
            'warm' => 50,     // Access count for warm data
        ],
    ],
    
    // Cache statistics tracking
    'statistics' => [
        'enabled' => true,
        'ttl' => 86400,       // Keep stats for 24 hours
        'detailed' => false,  // Track per-key statistics
    ],
    
    // Debug mode for development
    'debug' => env('CACHE_MAGIC_DEBUG', false),
    'log_channel' => 'single',
    
    // Cache warming configuration
    'warming' => [
        'enabled' => false,
        'schedule' => '0 * * * *', // Hourly
        'queries' => [],
    ],
    
    // API middleware configuration
    'middleware' => [
        'enabled' => false,
        'ttl' => 300,         // 5 minutes
        'methods' => ['GET'],
        'exclude_params' => ['token', 'api_key'],
    ],
];
```

---

## Basic Usage

### Simple Query Caching
```php
use App\Models\User;

// Basic caching - uses default TTL
$users = User::where('active', true)->cache()->get();

// Cache for specific duration (2 hours)
$users = User::where('active', true)->cache(7200)->get();

// Cache with custom key
$users = User::where('active', true)
    ->cache()
    ->key('active_users')
    ->get();
```

### Method Chaining
```php
$products = Product::where('category', 'electronics')
    ->with(['images', 'reviews'])
    ->cache()                    // Enable caching
    ->ttl(3600)                  // 1 hour
    ->tags(['products', 'electronics'])  // Add tags
    ->key('electronics_products') // Custom key
    ->debug()                    // Enable debug logging
    ->get();                     // Execute query
```

---

## Query Methods

### Supported Query Execution Methods

| Method | Description | Return Type | Example |
|--------|-------------|-------------|---------|
| `get()` | Get collection of models | `Collection` | `->cache()->get()` |
| `first()` | Get first model | `Model\|null` | `->cache()->first()` |
| `count()` | Count records | `int` | `->cache()->count()` |
| `exists()` | Check if records exist | `bool` | `->cache()->exists()` |
| `sum($column)` | Sum column values | `numeric` | `->cache()->sum('price')` |
| `avg($column)` | Average of column | `numeric` | `->cache()->avg('rating')` |
| `max($column)` | Maximum value | `mixed` | `->cache()->max('price')` |
| `min($column)` | Minimum value | `mixed` | `->cache()->min('price')` |
| `pluck($column)` | Get column values | `Collection` | `->cache()->pluck('email')` |

### Configuration Methods

| Method | Description | Example |
|--------|-------------|---------|
| `cache()` | Enable caching | `Model::cache()->get()` |
| `doNotCache()` | Disable caching for a query | `Model::cache()->doNotCache()->get()` |
| `cache($ttl)` | Cache with TTL in seconds | `Model::cache(3600)->get()` |
| `cache($key)` | Cache with custom key | `Model::cache('my_key')->get()` |
| `ttl($seconds)` | Set cache duration | `->ttl(7200)` |
| `tags($array)` | Add cache tags | `->tags(['users', 'active'])` |
| `key($string)` | Set custom cache key | `->key('custom_key')` |
| `refresh()` | Force refresh cache | `->refresh()->get()` |
| `debug()` | Enable debug logging | `->debug()->get()` |
| `version($string)` | Set cache version | `->version('2')` |
| `adaptive()` | Enable adaptive TTL | `->adaptive()->get()` |
| `refreshAsync()` | Refresh in background | `->refreshAsync()->get()` |
| `warmUp()` | Pre-load cache | `->warmUp()` |

### Macro Methods

| Method | Description | Example |
|--------|-------------|---------|
| `cacheForever()` | Cache indefinitely | `Model::cacheForever()->get()` |
| `cacheFor($minutes)` | Cache for minutes | `Model::cacheFor(30)->get()` |
| `dontCache()` | Disable caching | `Model::dontCache()->get()` |

---

## Cache Tags Explained

### What Are Cache Tags?

Cache tags are labels that group related cache entries together. They allow you to clear multiple cache entries at once without knowing their individual keys.

### How Tags Work

```php
// When you cache with tags
$projects = Project::where('status', 'active')
    ->cache()
    ->tags([
        'projects',                    // General tag
        'project:' . $project->id,     // Specific project
        'user:' . $user->id,           // User-specific
        'workspace:' . $workspace->id   // Workspace-specific
    ])
    ->get();
```

### Tag Invalidation Examples

```php
// Clear ALL project caches
Cache::tags(['projects'])->flush();

// Clear specific user's cache
Cache::tags(['user:123'])->flush();

// Clear specific project cache
Cache::tags(['project:456'])->flush();

// Clear multiple tags
Cache::tags(['projects', 'active'])->flush();
```

### Real-World Tag Usage

#### E-Commerce Example
```php
class ProductController
{
    public function index(Request $request)
    {
        $tags = ['products'];
        $query = Product::query();
        
        // Add category tag if filtered
        if ($category = $request->get('category')) {
            $query->where('category_id', $category);
            $tags[] = 'category:' . $category;
        }
        
        // Add brand tag if filtered
        if ($brand = $request->get('brand')) {
            $query->where('brand_id', $brand);
            $tags[] = 'brand:' . $brand;
        }
        
        // Cache with all relevant tags
        $products = $query->cache()
            ->tags($tags)
            ->ttl(1800) // 30 minutes
            ->get();
        
        return view('products.index', compact('products'));
    }
    
    public function update(Product $product)
    {
        $product->update(request()->all());
        
        // Clear relevant caches
        Cache::tags(['products'])->flush();                     // All products
        Cache::tags(['category:' . $product->category_id])->flush(); // Category
        Cache::tags(['brand:' . $product->brand_id])->flush();      // Brand
    }
}
```

#### Multi-Tenant SaaS Example
```php
// Cache with tenant isolation
$invoices = Invoice::where('tenant_id', $tenantId)
    ->cache()
    ->tags([
        'invoices',
        'tenant:' . $tenantId,
        'tenant:' . $tenantId . ':invoices'
    ])
    ->get();

// Clear all cache for a tenant
Cache::tags(['tenant:' . $tenantId])->flush();

// Clear only invoices for a tenant
Cache::tags(['tenant:' . $tenantId . ':invoices'])->flush();
```

### Why Use Tags?

1. **Selective Invalidation**: Clear only what needs updating
2. **Performance**: Don't clear everything when one thing changes
3. **Multi-tenancy**: Isolate cache by user/workspace/tenant
4. **Bulk Operations**: Clear related caches with one command

---

## Auto User Tags

### Overview
The package automatically adds user or guest tags to all cached queries, enabling user-specific cache management.

### Configuration
```php
// config/cache-magic.php
'auto_user_tags' => [
    'enabled' => true,  // Enable/disable auto user tags
    'guest_fallback' => 'session', // Options: 'session', 'ip', 'unique'
],
```

### Guest Fallback Strategies
- **`session`**: Uses session ID for guests (requires active session)
- **`ip`**: Uses hashed IP address (be careful with privacy regulations)
- **`unique`**: Always generates unique ID (no cache sharing between requests)

### How It Works
```php
// For authenticated users - automatically adds 'user:123' tag
$products = Product::cache()->get();

// For guests - automatically adds 'guest:session-id' tag
$products = Product::cache()->get();

// Clear cache for specific user
cache_clear_user(123);  // Clears all cache for user ID 123

// Clear cache for current user
cache_clear_user();  // Clears current user's cache

// Clear cache for guests
cache_clear_guest();  // Clears current guest session cache
```

---

## Disabling Cache with doNotCache()

### When to Use
Use `doNotCache()` when you need to bypass caching for specific queries, especially useful for:
- DataTables that require raw Eloquent builders
- Real-time data that should never be cached
- Debug or development scenarios

### Examples
```php
// Explicitly disable caching for a query
$users = User::doNotCache()->get();

// Use with DataTables
public function table($request): UsersTable
{
    // DataTables needs raw Eloquent Builder, not CacheQueryBuilder
    $query = User::query()->doNotCache();
    return new UsersTable($query);
}

// Disable caching in a scope
public function scopeGetRealTimeData($query)
{
    return $query->doNotCache()
        ->where('updated_at', '>', now()->subMinute());
}
```

---

## Model Integration

### Using CacheableTrait

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Fxcjahid\LaravelEloquentCacheMagic\Traits\CacheableTrait;

class Product extends Model
{
    use CacheableTrait;
    
    // Default cache duration in seconds (2 hours)
    protected $cacheExpiry = 7200;
    
    // Default cache tags for this model
    protected $cacheTags = ['products'];
    
    // Dynamic tags based on model attributes
    public function dynamicCacheTags(): array
    {
        return [
            'category:' . $this->category_id,
            'brand:' . $this->brand_id,
            'status:' . $this->status,
        ];
    }
    
    // Custom cache invalidation logic
    public function onCacheInvalidated(string $event): void
    {
        // Clear homepage cache when product changes
        if ($this->is_featured) {
            Cache::tags(['homepage'])->flush();
        }
        
        // Clear category page cache
        Cache::tags(['category:' . $this->category_id . ':page'])->flush();
    }
    
    // Define cacheable relationships
    public function cacheableRelations(): array
    {
        return ['images', 'reviews', 'category'];
    }
}
```

### Model Cache Methods

```php
// Find by ID with cache
$product = Product::findCached(123);
$product = Product::findCached(123, 7200); // Custom TTL

// Get all with cache
$products = Product::allCached();
$products = Product::allCached(3600); // 1 hour TTL

// Cache custom data for a model
$stats = $product->remember('stats', 3600, function() use ($product) {
    return [
        'views' => $product->views()->count(),
        'sales' => $product->orders()->sum('quantity'),
        'rating' => $product->reviews()->avg('rating'),
    ];
});

// Forget cached data
$product->forget('stats');

// Manually invalidate model cache
$product->invalidateModelCache();

// Warm up cache for this model
$product->warmUpCache();

// Get cache statistics for this model
$stats = $product->getCacheStatistics();
```

---

## Console Commands

### cache-magic:clear

Clear cache entries with various options:

```bash
# Clear all cache
php artisan cache-magic:clear --all

# Clear specific tags
php artisan cache-magic:clear --tags=products,users

# Clear specific key
php artisan cache-magic:clear --key=my_cache_key

# Clear model cache
php artisan cache-magic:clear --model=Product

# Clear statistics only
php artisan cache-magic:clear --stats
```

### cache-magic:stats

Display cache statistics and performance metrics:

```bash
# Show statistics
php artisan cache-magic:stats

# Export as JSON
php artisan cache-magic:stats --export

# Show live statistics (updates every second)
php artisan cache-magic:stats --live

# Show detailed statistics
php artisan cache-magic:stats --detailed

# Show statistics for specific key
php artisan cache-magic:stats --key=my_cache_key

# Show statistics for specific model
php artisan cache-magic:stats --model=Product

# Reset all statistics
php artisan cache-magic:stats --reset
```

### cache-magic:warm

Warm up cache by pre-loading queries:

```bash
# Warm using configuration
php artisan cache-magic:warm --config

# Warm specific models
php artisan cache-magic:warm --model=Product --model=User

# Force refresh even if cached
php artisan cache-magic:warm --force

# Run in parallel (if queue is configured)
php artisan cache-magic:warm --parallel
```

Configuration example for warming:
```php
// config/cache-magic.php
'warming' => [
    'enabled' => true,
    'schedule' => '0 */4 * * *', // Every 4 hours
    'queries' => [
        [
            'model' => \App\Models\Product::class,
            'method' => 'where',
            'args' => ['featured', true],
            'with' => ['images', 'reviews'],
            'cache_method' => 'get',
            'ttl' => 14400, // 4 hours
            'tags' => ['products', 'featured'],
            'description' => 'Featured products',
        ],
        [
            'model' => \App\Models\Category::class,
            'method' => 'whereNull',
            'args' => ['parent_id'],
            'with' => ['children'],
            'cache_method' => 'get',
            'ttl' => 86400, // 24 hours
            'tags' => ['categories', 'menu'],
            'description' => 'Main categories for menu',
        ],
    ],
],
```

---

## API Middleware

### Setup

Register middleware in `app/Http/Kernel.php`:

```php
protected $routeMiddleware = [
    // ...
    'cache.api' => \Fxcjahid\LaravelEloquentCacheMagic\Middleware\CacheApiResponse::class,
];
```

### Usage in Routes

```php
// Cache API responses for 5 minutes
Route::middleware(['cache.api:300'])->group(function () {
    Route::get('/api/products', [ProductController::class, 'index']);
    Route::get('/api/products/{id}', [ProductController::class, 'show']);
});

// Or in controller
class ProductController extends Controller
{
    public function __construct()
    {
        $this->middleware('cache.api:600')->only(['index', 'show']);
    }
}
```

### Configuration

```php
// config/cache-magic.php
'middleware' => [
    'enabled' => true,
    'ttl' => 300,           // Default 5 minutes
    'methods' => ['GET'],   // Only cache GET requests
    'exclude_params' => [   // Ignore these params in cache key
        'token',
        'api_key',
        '_',
        'timestamp',
    ],
    'tags' => ['api', 'responses'],
    'cache_authenticated' => false, // Don't cache for authenticated users
    'max_response_size' => 1048576, // 1MB max response size
],
```

### Response Headers

Cached responses include these headers:
- `X-Cache: HIT` or `X-Cache: MISS`
- `X-Cache-Time: 2024-01-15T10:30:00Z` (when cached)

---

## Statistics & Monitoring

### Cache Statistics

```php
use Fxcjahid\LaravelEloquentCacheMagic\Monitoring\CacheStatistics;

$stats = app(CacheStatistics::class);

// Get global statistics
$global = $stats->getGlobalStats();
// Returns: [
//     'hits' => 15420,
//     'misses' => 1580,
//     'writes' => 1580,
//     'total_requests' => 17000,
//     'hit_rate' => '90.71%',
//     'miss_rate' => '9.29%',
// ]

// Get statistics for specific key
$keyStats = $stats->getKeyStats('products:featured');

// Get model statistics
$modelStats = $stats->getModelStats(Product::class);

// Generate report
$report = $stats->generateReport();

// Export all statistics
$export = $stats->export();

// Reset statistics
$stats->reset();
```

### Cache Health Monitoring

```php
use Fxcjahid\LaravelEloquentCacheMagic\Monitoring\CacheHealth;

$health = app(CacheHealth::class);

// Perform health check
$status = $health->check();
// Returns: [
//     'status' => 'healthy', // or 'warning', 'critical'
//     'checks' => [
//         'driver' => [...],
//         'performance' => [...],
//         'hit_rate' => [...],
//         'memory' => [...],
//         'connection' => [...],
//     ],
//     'metrics' => [...],
//     'recommendations' => [...],
// ]
```

### Dashboard Integration

```php
// In your admin dashboard controller
public function dashboard()
{
    $cacheStats = app(CacheStatistics::class)->getGlobalStats();
    $cacheHealth = app(CacheHealth::class)->check();
    
    return view('admin.dashboard', [
        'cacheHitRate' => $cacheStats['hit_rate'],
        'cacheStatus' => $cacheHealth['status'],
        'cacheRecommendations' => $cacheHealth['recommendations'],
    ]);
}
```

---

## Helper Functions

### Global Helper Functions

```php
// Get Cache Magic instance
$manager = cache_magic();

// Cache a callback result
$result = cache_remember(function() {
    return expensive_operation();
}, ['ttl' => 3600, 'tags' => ['expensive']]);

// Clear cache by tags
cache_clear_tags(['products', 'featured']);

// Clear model cache
cache_clear_model(Product::class);

// Get cache statistics
$stats = cache_statistics();

// Check cache health
$health = cache_health();

// Check if driver supports tags
$supportsTags = cache_supports_tags();

// Warm model cache
cache_warm_model(Product::class, [
    ['method' => 'where', 'args' => ['featured', true]],
]);

// Clear cache for specific user
cache_clear_user(123);  // User ID 123

// Clear cache for current user
cache_clear_user();

// Clear cache for guest session
cache_clear_guest();

// Clear all users' cache
cache_clear_all_users();

// Clear all guests' cache
cache_clear_all_guests();
```

### Facade Usage

```php
use Fxcjahid\LaravelEloquentCacheMagic\Facades\CacheMagic;

// Get statistics
$stats = CacheMagic::statistics()->getGlobalStats();

// Check health
$health = CacheMagic::health()->check();

// Clear tags
CacheMagic::clearTags(['products']);

// Clear model
CacheMagic::clearModel(Product::class);

// Check if enabled
if (CacheMagic::isEnabled()) {
    // Caching is active
}

// Get cache size
$size = CacheMagic::size();

// Optimize cache
CacheMagic::optimize();
```

---

## Advanced Features

### Adaptive TTL

Automatically adjusts cache duration based on access frequency:

```php
// Enable for specific query
$products = Product::popular()
    ->cache()
    ->adaptive()  // Enable adaptive TTL
    ->get();

// How it works:
// - Frequently accessed (>100 hits): Longer TTL (up to max_ttl)
// - Moderately accessed (>50 hits): Normal TTL
// - Rarely accessed (<50 hits): Shorter TTL (down to min_ttl)

// Configure in config/cache-magic.php
'adaptive_ttl' => [
    'enabled' => true,    // Enable globally
    'min_ttl' => 300,     // 5 minutes minimum
    'max_ttl' => 86400,   // 24 hours maximum
    'thresholds' => [
        'hot' => 100,     // Hits for "hot" data
        'warm' => 50,     // Hits for "warm" data
    ],
],
```

### Async Cache Refresh

Refresh cache in background without blocking the request:

```php
// Refresh cache asynchronously
Product::where('featured', true)
    ->cache()
    ->refreshAsync()  // Dispatches job to refresh cache
    ->get();         // Returns current cached data immediately

// Configure queue in config/cache-magic.php
'async' => [
    'enabled' => true,
    'queue' => 'cache',      // Queue name
    'connection' => 'redis',  // Queue connection
],
```

### Cache Versioning

Invalidate all cache by changing version:

```php
// Set version for specific query
$products = Product::active()
    ->cache()
    ->version('2.0')  // All v1.0 cache becomes invalid
    ->get();

// Or globally in .env
CACHE_MAGIC_VERSION=2.0
```

### Custom Cache Drivers

```php
// Register custom driver in config/cache-magic.php
'custom_drivers' => [
    'my-driver' => \App\Cache\MyCustomDriver::class,
],
```

### Performance Optimization

```php
// Disable statistics for better performance
'statistics' => [
    'enabled' => false,  // No overhead
],

// Or use sampling (track only 10% of requests)
'statistics' => [
    'enabled' => true,
    'sample_rate' => 0.1,  // 10% sampling
],

// Conditional caching
$products = Product::when(app()->environment('production'), function ($query) {
    return $query->cache(3600);
})->get();
```

---

## Troubleshooting

### Common Issues and Solutions

#### Cache Tags Not Working

**Problem**: `Cache::supportsTags()` returns false

**Solution**: Install Redis or Memcached
```bash
# For Redis
composer require predis/predis

# For Memcached
pecl install memcached
```

#### Cache Not Invalidating

**Problem**: Cache doesn't clear on model updates

**Solution**: Ensure model uses CacheableTrait
```php
class User extends Model
{
    use \Fxcjahid\LaravelEloquentCacheMagic\Traits\CacheableTrait;
}
```

#### High Memory Usage

**Problem**: Cache consuming too much memory

**Solutions**:
1. Reduce TTL values
2. Use Redis instead of file driver
3. Enable cache eviction policies
4. Clear old cache regularly

#### Performance Issues

**Problem**: Slow cache operations

**Solutions**:
1. Switch to Redis/Memcached
2. Disable detailed statistics
3. Use cache warming
4. Enable adaptive TTL

### Debug Mode

Enable debug mode to troubleshoot:

```php
// For specific query
Product::where('active', true)
    ->cache()
    ->debug()  // Logs all cache operations
    ->get();

// Globally in .env
CACHE_MAGIC_DEBUG=true
CACHE_MAGIC_LOG_CHANNEL=cache

// Check logs at: storage/logs/cache.log
```

### Testing Cache

```php
// Check cache configuration
dd([
    'driver' => config('cache.default'),
    'supports_tags' => Cache::supportsTags(),
    'cache_enabled' => config('cache-magic.enabled'),
]);

// Test cache operations
$key = 'test_' . uniqid();
Cache::put($key, 'test', 60);
dd(Cache::get($key)); // Should return 'test'

// Test with tags (Redis/Memcached only)
if (Cache::supportsTags()) {
    Cache::tags(['test'])->put('tagged', 'value', 60);
    dd(Cache::tags(['test'])->get('tagged'));
}
```

### Performance Benchmarks

Typical performance improvements:

| Operation | Without Cache | With Cache (File) | With Cache (Redis) |
|-----------|--------------|-------------------|-------------------|
| Simple Query | 50ms | 5ms | 1ms |
| Complex Join | 200ms | 10ms | 2ms |
| With Relations | 300ms | 15ms | 3ms |
| Count Query | 100ms | 3ms | 0.5ms |

---

## Support

- **Email**: fxcjahid3@gmail.com
- **GitHub Issues**: https://github.com/fxcjahid/laravel-eloquent-cache-magic/issues
- **Documentation**: This file
- **Author**: FXC Jahid

---

## License

MIT License - see LICENSE file for details.