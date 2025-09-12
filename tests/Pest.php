<?php

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
*/

use Fxcjahid\LaravelEloquentCacheMagic\Tests\TestCase;

uses(TestCase::class)->in('Feature', 'Unit');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
*/

expect()->extend('toBeCached', function () {
    // Custom expectation for checking if something is cached
    return $this->toBeTrue();
});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
*/

function setupRedisCache()
{
    config(['cache.default' => 'redis']);
    config(['cache.stores.redis.connection' => 'cache']);
}

function setupFileCache()
{
    config(['cache.default' => 'file']);
}

function clearAllCache()
{
    \Illuminate\Support\Facades\Cache::flush();
}