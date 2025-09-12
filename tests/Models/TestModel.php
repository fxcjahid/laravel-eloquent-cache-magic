<?php

namespace Fxcjahid\LaravelEloquentCacheMagic\Tests\Models;

use Illuminate\Database\Eloquent\Model;
use Fxcjahid\LaravelEloquentCacheMagic\Traits\CacheableTrait;

class TestModel extends Model
{
    use CacheableTrait;
    
    protected $table = 'test_models';
    
    protected $fillable = ['name', 'active'];
    
    protected $cacheExpiry = 3600;
    
    protected $cacheTags = ['test_models'];
}