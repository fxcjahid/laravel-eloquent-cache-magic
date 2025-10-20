# Laravel Eloquent Cache Magic 🪄

[![Latest Version](https://img.shields.io/github/v/release/fxcjahid/laravel-eloquent-cache-magic)](https://github.com/fxcjahid/laravel-eloquent-cache-magic/releases)
[![License](https://img.shields.io/github/license/fxcjahid/laravel-eloquent-cache-magic)](https://github.com/fxcjahid/laravel-eloquent-cache-magic/blob/main/LICENSE)
[![PHP Version](https://img.shields.io/packagist/php-v/fxcjahid/laravel-eloquent-cache-magic)](https://packagist.org/packages/fxcjahid/laravel-eloquent-cache-magic)
[![Laravel Version](https://img.shields.io/badge/Laravel-10.x%20|%2011.x%20|%2012.x-orange)](https://laravel.com)

A lightweight Laravel package that adds intelligent caching to your Eloquent queries with zero effort. Features include automatic cache invalidation, Redis/Memcached tag support, and flexible caching options!

📖 **[Read Complete Documentation](./DOCUMENTATION.md)** - Everything you need to know in one place

## ✨ Features

- 🚀 **Zero Configuration** - Works out of the box with sensible defaults
- 🎉 **Automatic Query Caching** - All queries automatically cached without code changes!
- 🏷️ **Cache Tags Support** - Full support for Redis and Memcached tagged caching
- 🔄 **Automatic Cache Invalidation** - Cache automatically clears on model create, update, delete
- 🔧 **Flexible API** - Multiple ways to configure caching per query
- 🌐 **Multi-Driver Support** - Works with Redis, Memcached, File, Database drivers
- 🧪 **Fully Tested** - Comprehensive test coverage with PHPUnit and Pest
- 👤 **Auto User/Guest Tags** - Automatic user-specific cache isolation
- 🚫 **doNotCache() Method** - Disable caching for specific queries (DataTables compatible)
- 🎯 **Helper Functions** - Convenient global functions for cache operations

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
CACHE_DRIVER=redis
REDIS_HOST=127.0.0.1
REDIS_PASSWORD=null
REDIS_PORT=6379
```

#### Also update config/database.php

```php
'redis' => [
    'client' => env('REDIS_CLIENT', 'predis'),
]
```

## 🚀 Quick Start

### 🎉 Automatic Query Caching

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

### 🎉 Automatic Query Caching

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

### User/Guest Isolation

Automatically isolate cache by user or guest session:

```php
// In config/cache-magic.php
'auto_user_tags' => [
    'enabled' => true,
    'guest_fallback' => 'session', // Options: 'session', 'ip', 'unique'
],
```

## 📊 Console Commands

```bash
# Clear cache by tags
php artisan cache-magic:clear --tags=products

# Clear cache by model
php artisan cache-magic:clear --model=Product

# Clear all cache
php artisan cache-magic:clear --all
```

## 🔧 Helper Functions

```php
// Cache a callback result
$users = cache_remember(['ttl' => 3600, 'tags' => ['users']], function() {
    return User::all();
});

// Clear cache by tags
cache_clear_tags(['products', 'electronics']);

// Clear cache by model
cache_clear_model(Product::class);

// Clear user-specific cache
cache_clear_user($userId);

// Clear guest cache
cache_clear_guest($guestId);

// Check if cache supports tags
if (cache_supports_tags()) {
    // Use tag-based caching
}
```

## 📖 Complete Documentation

**[Read the complete documentation](./DOCUMENTATION.md)** for comprehensive details on:

- ✅ Installation and configuration
- ✅ All query methods and parameters
- ✅ Cache tags explained with examples
- ✅ Model integration guide
- ✅ Console commands usage
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
