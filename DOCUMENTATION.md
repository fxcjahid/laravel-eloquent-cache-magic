# Laravel Eloquent Cache Magic - Complete Documentation

## Table of Contents

1. [Installation](#installation)
2. [Configuration](#configuration)
3. [Automatic Query Caching](#automatic-query-caching)
4. [Basic Usage](#basic-usage)
5. [Query Methods](#query-methods)
6. [Cache Tags Explained](#cache-tags-explained)
7. [Model Integration](#model-integration)
8. [Console Commands](#console-commands)
9. [Helper Functions](#helper-functions)
10. [Advanced Features](#advanced-features)
11. [Troubleshooting](#troubleshooting)

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

Update `config/database.php`:

```php
'redis' => [
    'client' => env('REDIS_CLIENT', 'predis'),
]
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

After publishing, you'll find the configuration file at `config/cache-magic.php`:

```php
return [
    // Enable or disable caching globally
    'enabled' => env('CACHE_MAGIC_ENABLED', true),

    // Default cache TTL in seconds (1 hour)
    'default_ttl' => 3600,

    // Cache version (increment to invalidate all caches)
    'version' => '1',

    // Global tags applied to all cached queries
    'global_tags' => ['app'],

    // Automatic cache invalidation on model events
    'auto_invalidation' => [
        'enabled' => true,
    ],

    // Automatic query caching
    'auto_cache' => [
        'enabled' => true,
        'ttl' => 3600,              // Default TTL for auto-cached queries
        'aggregate_ttl' => 300,     // TTL for count/sum/avg queries
        'find_ttl' => 7200,         // TTL for find/findOrFail queries
    ],

    // Debug mode for detailed logging
    'debug' => env('CACHE_MAGIC_DEBUG', false),

    // Automatic user/guest cache isolation
    'auto_user_tags' => [
        'enabled' => true,
        'guest_fallback' => 'session', // Options: 'session', 'ip', 'unique'
    ],
];
```

### Configuration Options Explained

| Option | Type | Default | Description |
|--------|------|---------|-------------|
| `enabled` | bool | true | Master switch for caching |
| `default_ttl` | int | 3600 | Default cache duration in seconds |
| `version` | string | '1' | Cache version for easy invalidation |
| `global_tags` | array | ['app'] | Tags applied to all caches |
| `auto_invalidation.enabled` | bool | true | Auto-clear cache on model changes |
| `auto_cache.enabled` | bool | true | Enable automatic query caching |
| `auto_cache.ttl` | int | 3600 | Default auto-cache duration |
| `auto_cache.aggregate_ttl` | int | 300 | TTL for count/sum/avg |
| `auto_cache.find_ttl` | int | 7200 | TTL for find operations |
| `debug` | bool | false | Enable debug logging |
| `auto_user_tags.enabled` | bool | true | Isolate cache by user/guest |
| `auto_user_tags.guest_fallback` | string | 'session' | Guest identification strategy |

---

## Automatic Query Caching

### Zero-Code Caching

Laravel Eloquent Cache Magic can automatically cache ALL your Eloquent queries without requiring any code changes.

### How It Works

When enabled, the package automatically:

1. **Intercepts all Eloquent queries** through a custom query builder
2. **Caches SELECT operations** with smart TTL management
3. **Skips write operations** (INSERT, UPDATE, DELETE)
4. **Invalidates cache** on model changes

### Enabling Auto-Cache

Auto-cache is enabled by default. Configure it in `config/cache-magic.php`:

```php
'auto_cache' => [
    'enabled' => true,              // Master switch for auto-caching
    'ttl' => 3600,                  // Default TTL in seconds (1 hour)
    'aggregate_ttl' => 300,         // TTL for count/sum/avg queries (5 min)
    'find_ttl' => 7200,             // TTL for find/findOrFail queries (2 hours)
],
```

### Usage Examples

```php
// These are ALL automatically cached!
$users = User::all();                           // Cached for 1 hour
$user = User::find(1);                          // Cached for 2 hours
$count = Product::where('active', true)->count(); // Cached for 5 minutes
$total = Order::sum('amount');                  // Cached for 5 minutes

// Bypass auto-cache for fresh data
$freshUsers = User::withoutCache()->all();      // Skip cache
$freshUser = User::fresh()->find(1);            // Skip cache

// Disable auto-cache per model
class Order extends Model {
    use CacheableTrait;
    protected $autoCache = false;  // This model won't auto-cache
}
```

### Smart TTL Management

Auto-cache uses intelligent TTL based on query type:

- **Regular queries** (`get`, `all`, `first`): 1 hour (default)
- **Find operations** (`find`, `findOrFail`): 2 hours
- **Aggregate functions** (`count`, `sum`, `avg`, `max`, `min`): 5 minutes

---

## Basic Usage

### Simple Query Caching

```php
use App\Models\User;

// Cache query results
$users = User::where('active', true)->cache()->get();

// Cache for specific duration (seconds)
$users = User::where('active', true)->cache(7200)->get(); // 2 hours

// Cache with options
$users = User::where('active', true)
    ->cache([
        'ttl' => 3600,
        'tags' => ['users', 'active'],
        'key' => 'active-users',
    ])
    ->get();
```

### Working with Different Query Types

```php
// Get results
$products = Product::cache()->get();

// First result
$product = Product::cache()->first();

// Find by ID
$product = Product::cache()->find(1);

// Count
$count = User::where('active', true)->cache()->count();

// Aggregate functions
$total = Order::cache()->sum('amount');
$average = Product::cache()->avg('price');
$max = Product::cache()->max('price');
$min = Product::cache()->min('price');

// Exists
$exists = User::where('email', 'test@example.com')->cache()->exists();

// Pluck
$names = User::cache()->pluck('name', 'id');
```

---

## Query Methods

### Available Methods

| Method | Description | Example |
|--------|-------------|---------|
| `cache()` | Enable caching with default TTL | `->cache()->get()` |
| `cache($ttl)` | Cache with specific TTL (seconds) | `->cache(3600)->get()` |
| `cache($options)` | Cache with options array | `->cache(['ttl' => 3600])->get()` |
| `ttl($seconds)` | Set cache TTL | `->cache()->ttl(7200)->get()` |
| `tags($tags)` | Add cache tags | `->cache()->tags(['users'])->get()` |
| `key($key)` | Custom cache key | `->cache()->key('my-key')->get()` |
| `refresh()` | Force cache refresh | `->cache()->refresh()->get()` |
| `clearCache()` | Clear cache for this query | `->cache()->clearCache()` |
| `doNotCache()` | Disable caching | `->doNotCache()->get()` |
| `withoutCache()` | Skip cache (auto-cache only) | `->withoutCache()->get()` |
| `fresh()` | Alias for withoutCache() | `->fresh()->get()` |

### Method Chaining Examples

```php
// Multiple options
$users = User::where('active', true)
    ->cache()
    ->ttl(7200)
    ->tags(['users', 'active'])
    ->key('active-users')
    ->get();

// Force refresh
$users = User::cache()->refresh()->get();

// Disable caching for specific query
$users = User::where('admin', true)->doNotCache()->get();

// Clear cache manually
User::where('active', true)->cache()->clearCache();
```

---

## Cache Tags Explained

### What are Cache Tags?

Cache tags allow you to group related cached items together for easy invalidation. Only **Redis** and **Memcached** support tags.

### Basic Tagging

```php
// Cache with tags
$products = Product::cache()
    ->tags(['products', 'electronics'])
    ->get();

// Clear all caches with 'electronics' tag
Cache::tags(['electronics'])->flush();

// Clear multiple tags
Cache::tags(['products'])->flush();
```

### Automatic Model Tags

When using `CacheableTrait`, model tags are automatically applied:

```php
class Product extends Model
{
    use CacheableTrait;

    protected $cacheTags = ['products'];
}

// Automatically tagged with 'products'
$products = Product::cache()->get();

// Clear all product caches
Cache::tags(['products'])->flush();
```

### Dynamic Tags

Generate tags dynamically based on model attributes:

```php
class Product extends Model
{
    use CacheableTrait;

    protected $cacheTags = ['products'];

    public function dynamicCacheTags(): array
    {
        return [
            'category:' . $this->category_id,
            'brand:' . $this->brand_id,
        ];
    }
}

// Automatically tagged with: ['products', 'category:5', 'brand:10']
```

### User/Guest Isolation

Automatically isolate cache per user or guest:

```php
// In config/cache-magic.php
'auto_user_tags' => [
    'enabled' => true,
    'guest_fallback' => 'session', // Options: 'session', 'ip', 'unique'
],

// Authenticated users
// Tag: 'user:123'
$orders = Order::where('user_id', auth()->id())->cache()->get();

// Guests
// Tag: 'guest:session_id_here'
$cart = Cart::where('session_id', session()->getId())->cache()->get();

// Clear user-specific cache
cache_clear_user(123);

// Clear guest cache
cache_clear_guest(session()->getId());
```

---

## Model Integration

### Using CacheableTrait

Add caching capabilities to your models:

```php
use Illuminate\Database\Eloquent\Model;
use Fxcjahid\LaravelEloquentCacheMagic\Traits\CacheableTrait;

class Product extends Model
{
    use CacheableTrait;

    // Define cache TTL (optional)
    protected $cacheExpiry = 7200; // 2 hours

    // Define cache tags (optional)
    protected $cacheTags = ['products'];

    // Disable auto-cache for this model (optional)
    protected $autoCache = false;
}
```

### Automatic Cache Invalidation

Cache is automatically cleared when models are created, updated, or deleted:

```php
// Cache is automatically invalidated
$product = Product::find(1);
$product->price = 99.99;
$product->save(); // Cache cleared!

$product->delete(); // Cache cleared!

Product::create(['name' => 'New Product']); // Cache cleared!
```

### Custom Cache Tags

```php
class Product extends Model
{
    use CacheableTrait;

    protected $cacheTags = ['products'];

    // Dynamic tags based on attributes
    public function dynamicCacheTags(): array
    {
        return [
            'category:' . $this->category_id,
            'brand:' . $this->brand_id,
            'price-range:' . $this->getPriceRange(),
        ];
    }

    protected function getPriceRange(): string
    {
        if ($this->price < 50) return 'low';
        if ($this->price < 200) return 'medium';
        return 'high';
    }
}
```

### Convenience Methods

```php
// Find with cache
$product = Product::findCached(1);

// Get all with cache
$products = Product::allCached();

// Remember value with model tags
$value = $product->remember('expensive-calculation', 3600, function() {
    return $this->calculateComplexValue();
});

// Forget cached value
$product->forget('expensive-calculation');
```

---

## Console Commands

### Clear Cache Command

```bash
# Clear all cache
php artisan cache-magic:clear --all

# Clear by tags
php artisan cache-magic:clear --tags=products --tags=electronics

# Clear by model
php artisan cache-magic:clear --model=Product

# Clear by key
php artisan cache-magic:clear --key=specific-cache-key

# Combine options
php artisan cache-magic:clear --tags=products --model=User
```

### Command Options

| Option | Description | Example |
|--------|-------------|---------|
| `--all` | Clear all cache | `--all` |
| `--tags=` | Clear by tag(s) | `--tags=products --tags=users` |
| `--model=` | Clear by model | `--model=Product` |
| `--key=` | Clear specific key | `--key=my-cache-key` |

---

## Helper Functions

### Available Helpers

#### cache_remember()

Cache a callback result:

```php
$users = cache_remember(['ttl' => 3600, 'tags' => ['users']], function() {
    return User::all();
});

// With options
$data = cache_remember([
    'key' => 'my-data',
    'ttl' => 7200,
    'tags' => ['custom-tag'],
], function() {
    return expensive_operation();
});
```

#### cache_clear_tags()

Clear cache by tags:

```php
cache_clear_tags(['products', 'electronics']);
```

#### cache_clear_model()

Clear all cache for a model:

```php
cache_clear_model(Product::class);
cache_clear_model('App\Models\Product');
```

#### cache_clear_user()

Clear user-specific cache:

```php
// Clear current user's cache
cache_clear_user();

// Clear specific user's cache
cache_clear_user(123);
```

#### cache_clear_guest()

Clear guest cache:

```php
// Clear current guest's cache
cache_clear_guest();

// Clear specific guest's cache
cache_clear_guest($sessionId);
```

#### cache_clear_all_users()

Clear all user caches:

```php
cache_clear_all_users();
```

#### cache_clear_all_guests()

Clear all guest caches:

```php
cache_clear_all_guests();
```

#### cache_supports_tags()

Check if current cache driver supports tags:

```php
if (cache_supports_tags()) {
    // Use tag-based caching
    cache_clear_tags(['products']);
} else {
    // Use alternative method
    Cache::flush();
}
```

---

## Advanced Features

### Custom Cache Keys

```php
// Auto-generated key based on query
$users = User::where('active', true)->cache()->get();

// Custom key
$users = User::where('active', true)
    ->cache()
    ->key('active-users-list')
    ->get();

// Dynamic key
$categoryId = 5;
$products = Product::where('category_id', $categoryId)
    ->cache()
    ->key("category-{$categoryId}-products")
    ->get();
```

### Cache Versioning

Invalidate all caches by changing version:

```php
// In config/cache-magic.php
'version' => '2', // Change from '1' to '2' to invalidate all

// Per query
$users = User::cache()
    ->version('v2')
    ->get();
```

### Force Refresh

Force cache refresh while keeping the cache structure:

```php
// Refresh cache
$users = User::cache()->refresh()->get();

// Same as:
$users = User::cache()->refresh(true)->get();
```

### Disable Caching Selectively

```php
// For specific query
$users = User::where('admin', true)->doNotCache()->get();

// For auto-cached queries
$users = User::withoutCache()->all();
$user = User::fresh()->find(1);

// For specific model
class Order extends Model
{
    use CacheableTrait;
    protected $autoCache = false; // Never auto-cache this model
}
```

### Debug Mode

Enable debug logging:

```php
// In config/cache-magic.php
'debug' => true,

// Or per query
$users = User::cache()->debug(true)->get();
```

### Global Tags

Apply tags to all cached queries:

```php
// In config/cache-magic.php
'global_tags' => ['app', 'production'],
```

---

## Troubleshooting

### Cache Not Working

**Problem**: Queries are not being cached.

**Solutions**:
1. Check if caching is enabled:
   ```php
   // config/cache-magic.php
   'enabled' => true,
   ```

2. Verify cache driver is configured:
   ```bash
   php artisan config:cache
   ```

3. Check cache connection:
   ```php
   Cache::put('test', 'value', 60);
   dd(Cache::get('test')); // Should return 'value'
   ```

### Tags Not Working

**Problem**: Tag-based cache clearing doesn't work.

**Solutions**:
1. Verify you're using Redis or Memcached:
   ```env
   CACHE_DRIVER=redis
   ```

2. Check tag support:
   ```php
   if (!cache_supports_tags()) {
       echo "Current driver doesn't support tags!";
   }
   ```

3. For file/database drivers, use `Cache::flush()` instead.

### Auto-Cache Not Working

**Problem**: Queries are not automatically cached.

**Solutions**:
1. Enable auto-cache:
   ```php
   // config/cache-magic.php
   'auto_cache' => [
       'enabled' => true,
   ],
   ```

2. Check model configuration:
   ```php
   class Product extends Model
   {
       use CacheableTrait;
       // Make sure this is true or not set
       protected $autoCache = true;
   }
   ```

### Cache Not Invalidating

**Problem**: Cache doesn't clear when models are updated.

**Solutions**:
1. Enable auto-invalidation:
   ```php
   // config/cache-magic.php
   'auto_invalidation' => [
       'enabled' => true,
   ],
   ```

2. Use `CacheableTrait` on your models:
   ```php
   class Product extends Model
   {
       use CacheableTrait;
   }
   ```

3. Manually clear cache:
   ```php
   cache_clear_model(Product::class);
   Cache::tags(['products'])->flush();
   ```

### Performance Issues

**Problem**: Application is slow with caching enabled.

**Solutions**:
1. Reduce TTL for frequently changing data:
   ```php
   'default_ttl' => 300, // 5 minutes instead of 1 hour
   ```

2. Use selective caching instead of auto-cache:
   ```php
   'auto_cache' => [
       'enabled' => false,
   ],
   ```

3. Use tags for better cache management:
   ```php
   Product::cache()->tags(['products'])->get();
   ```

### Memory Issues

**Problem**: Redis/Memcached running out of memory.

**Solutions**:
1. Reduce TTL values
2. Use more specific tags
3. Clear unused caches regularly:
   ```bash
   php artisan cache-magic:clear --tags=old-data
   ```

4. Monitor cache size:
   ```php
   use Fxcjahid\LaravelEloquentCacheMagic\Facades\CacheMagic;
   $size = CacheMagic::size();
   ```

---

## Best Practices

### 1. Use Appropriate TTL

```php
// Fast-changing data: Short TTL
Order::cache(['ttl' => 300])->get(); // 5 minutes

// Stable data: Long TTL
Country::cache(['ttl' => 86400])->get(); // 24 hours
```

### 2. Use Tags for Organization

```php
// Group related caches
Product::cache()->tags(['products', 'catalog'])->get();
Category::cache()->tags(['categories', 'catalog'])->get();

// Clear entire catalog
Cache::tags(['catalog'])->flush();
```

### 3. Clear Cache on Important Updates

```php
class ProductController extends Controller
{
    public function update(Product $product)
    {
        $product->update($request->all());

        // Clear related caches
        cache_clear_tags(['products', 'catalog']);
    }
}
```

### 4. Use Auto-Cache Wisely

```php
// Enable for read-heavy models
class Product extends Model
{
    use CacheableTrait;
    protected $autoCache = true;
}

// Disable for write-heavy models
class Log extends Model
{
    use CacheableTrait;
    protected $autoCache = false;
}
```

### 5. Monitor Cache Performance

```php
// In development
'debug' => env('CACHE_MAGIC_DEBUG', false),
```

---

## Support

For issues, questions, or contributions:

- GitHub: https://github.com/fxcjahid/laravel-eloquent-cache-magic
- Email: fxcjahid3@gmail.com

## License

MIT License - see LICENSE file for details.
