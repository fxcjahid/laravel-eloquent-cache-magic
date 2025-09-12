<?php

namespace Fxcjahid\LaravelEloquentCacheMagic\Tests\Models;

use Illuminate\Database\Eloquent\Model;
use Fxcjahid\LaravelEloquentCacheMagic\Traits\CacheableTrait;

class TestPost extends Model
{
    use CacheableTrait;
    
    protected $table = 'test_posts';
    
    protected $fillable = ['title', 'content', 'user_id', 'status'];
    
    protected $cacheExpiry = 7200;
    
    protected $cacheTags = ['test_posts'];
    
    public function dynamicCacheTags(): array
    {
        return [
            'user:' . $this->user_id,
            'status:' . $this->status,
        ];
    }
}