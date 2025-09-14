# Laravel Eloquent Cache Magic 🪄

[![Latest Version](https://img.shields.io/github/v/release/fxcjahid/laravel-eloquent-cache-magic)](https://github.com/fxcjahid/laravel-eloquent-cache-magic/releases)
[![License](https://img.shields.io/github/license/fxcjahid/laravel-eloquent-cache-magic)](https://github.com/fxcjahid/laravel-eloquent-cache-magic/blob/main/LICENSE)
[![PHP Version](https://img.shields.io/packagist/php-v/fxcjahid/laravel-eloquent-cache-magic)](https://packagist.org/packages/fxcjahid/laravel-eloquent-cache-magic)
[![Laravel Version](https://img.shields.io/badge/Laravel-10.x%20|%2011.x%20|%2012.x-orange)](https://laravel.com)

A powerful Laravel package that adds automatic and intelligent caching to your Eloquent queries with zero effort. Features include automatic cache invalidation, Redis/Memcached tag support, cache statistics, and much more!

📖 **[Read Complete Documentation](./DOCUMENTATION.md)** - Everything you need to know in one place

## ✨ Features

- 🚀 **Zero Configuration** - Works out of the box with sensible defaults
- 🎉 **Automatic Query Caching** - All queries are automatically cached without any code changes!
- 🏷️ **Cache Tags Support** - Full support for Redis and Memcached tagged caching
- 🔄 **Automatic Cache Invalidation** - Cache automatically clears on model create, update, delete
- 📊 **Built-in Statistics** - Monitor cache hit rates and performance
- 🎯 **Adaptive TTL** - Automatically adjusts cache duration based on access patterns
- 🔧 **Flexible API** - Multiple ways to configure caching per query
- 🌐 **Multi-Driver Support** - Works with Redis, Memcached, File, Database drivers
- 🧪 **Fully Tested** - Comprehensive test coverage with PHPUnit and Pest
- 📈 **Performance Monitoring** - Track and optimize cache performance
- ⚡ **Async Cache Refresh** - Refresh cache in background jobs
- 👤 **Auto User/Guest Tags** - Automatic user-specific cache isolation
- 🚫 **doNotCache() Method** - Disable caching for specific queries (DataTables compatible)

## 📋 Requirements

- PHP 8.0 - 8.4
- Laravel 10.0 - 12.0
- Redis or Memcached (optional, for tag support)

## 📦 Installation

```bash
composer require fxcjahid/laravel-eloquent-cache-magic
```

### Optional: Publish Configuration

```bash
php artisan vendor:publish --tag=cache-magic-config
```

### Recommended: Setup Redis for Tag Support

```bash
composer require predis/predis
```

```env
CACHE_DRIVER=predis
REDIS_HOST=127.0.0.1
REDIS_PASSWORD=null
REDIS_PORT=6379
```

#### Also update config\database.php

```php
'redis' => [
    'client' => env('REDIS_CLIENT', 'predis'),
]
```

## 🚀 Quick Start

### 🎉 NEW: Automatic Query Caching (v0.2+)

With auto-cache enabled, ALL your queries are automatically cached without any code changes:

```php
use App\Models\User;
use App\Models\Product;

// These queries are AUTOMATICALLY cached (no ->cache() needed!)
$users = User::all();                          // Auto-cached!
$product = Product::find(1);                   // Auto-cached!
$count = User::where('active', true)->count(); // Auto-cached!

// Bypass auto-cache when you need fresh data
$freshUsers = User::withoutCache()->get();     // Skip cache
$freshProduct = Product::fresh()->find(1);     // Skip cache

// Disable auto-cache for specific models
class Order extends Model {
    use CacheableTrait;
    protected $autoCache = false;  // Disable auto-cache for this model
}
```

### Manual Caching (Traditional Method)

You can still manually control caching with the `->cache()` method:

```php
// Manually cache the query
$users = User::where('active', true)->cache()->get();

// Cache for specific duration (in seconds)
$users = User::where('active', true)->cache(3600)->get(); // 1 hour

// Cache with tags (Redis/Memcached only)
$users = User::where('active', true)
    ->cache()
    ->tags(['users', 'active'])
    ->get();

// Clear cache by tags
Cache::tags(['users'])->flush(); // Only clears 'users' tagged cache
```

### Model Integration

```php
use Illuminate\Database\Eloquent\Model;
use Fxcjahid\LaravelEloquentCacheMagic\Traits\CacheableTrait;

class Product extends Model
{
    use CacheableTrait;
  
    protected $cacheExpiry = 7200; // 2 hours
    protected $cacheTags = ['products'];
}
```

## 🎯 Key Features Explained

### 🎉 Automatic Query Caching (NEW!)

Enable automatic caching for ALL queries without changing your code:

```php
// In config/cache-magic.php
'auto_cache' => [
    'enabled' => true,              // Enable auto-caching
    'ttl' => 3600,                  // Default 1 hour
    'aggregate_ttl' => 300,         // 5 min for count/sum/avg
    'find_ttl' => 7200,             // 2 hours for find()
],
```

Once enabled, all your existing queries are automatically cached:

```php
// These are ALL automatically cached now!
User::all();                        // Cached for 1 hour
Product::find($id);                 // Cached for 2 hours
Order::count();                     // Cached for 5 minutes
Invoice::where('paid', true)->get(); // Cached for 1 hour

// Need fresh data? Bypass cache:
User::withoutCache()->all();        // Direct from database
Product::fresh()->find($id);        // Direct from database
```

### Cache Tags - Smart Invalidation

```php
// Cache with tags for smart invalidation
$products = Product::where('category', 'electronics')
    ->cache()
    ->tags(['products', 'electronics'])
    ->get();

// Clear all electronics products
Cache::tags(['electronics'])->flush();

// Clear all products
Cache::tags(['products'])->flush();
```

### Automatic Cache Invalidation

```php
class Product extends Model
{
    use CacheableTrait;
  
    // Cache automatically clears when model is updated/deleted
    protected $cacheTags = ['products'];
  
    // Dynamic tags based on attributes
    public function dynamicCacheTags(): array
    {
        return [
            'category:' . $this->category_id,
            'brand:' . $this->brand_id,
        ];
    }
}
```

### Performance Monitoring

```php
use Fxcjahid\LaravelEloquentCacheMagic\Facades\CacheMagic;

// Get cache statistics
$stats = CacheMagic::statistics()->getGlobalStats();
// Returns: ['hit_rate' => '92.5%', 'hits' => 15420, ...]

// Check cache health
$health = CacheMagic::health()->check();
// Returns: ['status' => 'healthy', 'checks' => [...]]
```

## 📊 Console Commands

```bash
# Clear cache
php artisan cache-magic:clear --tags=products

# View statistics
php artisan cache-magic:stats

# Warm cache
php artisan cache-magic:warm --model=Product
```

## 📖 Complete Documentation

**[Read the complete documentation](./DOCUMENTATION.md)** for comprehensive details on:

- ✅ All query methods and parameters
- ✅ Cache tags explained with examples
- ✅ Model integration guide
- ✅ Console commands usage
- ✅ API middleware setup
- ✅ Performance monitoring
- ✅ Helper functions
- ✅ Advanced features
- ✅ Troubleshooting guide

## 🧪 Testing

```bash
# Run tests
vendor/bin/pest

# With coverage
vendor/bin/pest --coverage
```

## 🤝 Contributing

Contributions are welcome! Please feel free to submit a Pull Request.

## 📝 License

This package is open-sourced software licensed under the [MIT license](LICENSE).

## 👨‍💻 Author

**FXC Jahid**

- GitHub: [@fxcjahid](https://github.com/fxcjahid)
- Email: fxcjahid3@gmail.com

## 🌟 Support

If you find this package helpful, please give it a ⭐ on [GitHub](https://github.com/fxcjahid/laravel-eloquent-cache-magic)!
