# Laravel Eloquent Cache Magic 🪄

[![Latest Version](https://img.shields.io/github/v/release/fxcjahid/laravel-eloquent-cache-magic)](https://github.com/fxcjahid/laravel-eloquent-cache-magic/releases)
[![License](https://img.shields.io/github/license/fxcjahid/laravel-eloquent-cache-magic)](https://github.com/fxcjahid/laravel-eloquent-cache-magic/blob/main/LICENSE)
[![PHP Version](https://img.shields.io/packagist/php-v/fxcjahid/laravel-eloquent-cache-magic)](https://packagist.org/packages/fxcjahid/laravel-eloquent-cache-magic)
[![Laravel Version](https://img.shields.io/badge/Laravel-9.x%20|%2010.x%20|%2011.x-orange)](https://laravel.com)

A powerful Laravel package that adds automatic and intelligent caching to your Eloquent queries with zero effort. Features include automatic cache invalidation, Redis/Memcached tag support, cache statistics, and much more!

📖 **[Read Complete Documentation](./DOCUMENTATION.md)** - Everything you need to know in one place

## ✨ Features

- 🚀 **Zero Configuration** - Works out of the box with sensible defaults
- 🏷️ **Cache Tags Support** - Full support for Redis and Memcached tagged caching
- 🔄 **Automatic Cache Invalidation** - Cache automatically clears on model create, update, delete
- 📊 **Built-in Statistics** - Monitor cache hit rates and performance
- 🎯 **Adaptive TTL** - Automatically adjusts cache duration based on access patterns
- 🔧 **Flexible API** - Multiple ways to configure caching per query
- 🌐 **Multi-Driver Support** - Works with Redis, Memcached, File, Database drivers
- 🧪 **Fully Tested** - Comprehensive test coverage with PHPUnit and Pest
- 📈 **Performance Monitoring** - Track and optimize cache performance
- ⚡ **Async Cache Refresh** - Refresh cache in background jobs

## 📋 Requirements

- PHP 8.0+
- Laravel 9.0+ / 10.0+ / 11.0+
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

## 🚀 Quick Start

### Basic Usage

```php
use App\Models\User;

// Automatically cache the query
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