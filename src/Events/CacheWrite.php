<?php

namespace Fxcjahid\LaravelEloquentCacheMagic\Events;

class CacheWrite
{
    public string $key;
    public array $tags;
    public int $ttl;
    
    public function __construct(string $key, array $tags = [], int $ttl = 3600)
    {
        $this->key = $key;
        $this->tags = $tags;
        $this->ttl = $ttl;
    }
}