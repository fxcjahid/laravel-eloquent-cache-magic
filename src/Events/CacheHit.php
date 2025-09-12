<?php

namespace Fxcjahid\LaravelEloquentCacheMagic\Events;

class CacheHit
{
    public string $key;
    public array $tags;
    
    public function __construct(string $key, array $tags = [])
    {
        $this->key = $key;
        $this->tags = $tags;
    }
}