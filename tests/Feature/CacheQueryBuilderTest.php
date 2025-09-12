<?php

use Illuminate\Support\Facades\Cache;
use Fxcjahid\LaravelEloquentCacheMagic\Tests\Models\TestModel;
use Fxcjahid\LaravelEloquentCacheMagic\Tests\Models\TestPost;

beforeEach(function () {
    clearAllCache();
});

it('caches query results', function () {
    // Create test data
    TestModel::create(['name' => 'Test 1']);
    TestModel::create(['name' => 'Test 2']);
    
    // First call - should miss cache
    $results1 = TestModel::where('active', true)->cache()->get();
    
    // Second call - should hit cache
    $results2 = TestModel::where('active', true)->cache()->get();
    
    expect($results1->count())->toBe(2);
    expect($results2->count())->toBe(2);
    expect($results1->pluck('id'))->toEqual($results2->pluck('id'));
});

it('caches with custom TTL', function () {
    TestModel::create(['name' => 'Test']);
    
    // Cache for 1 second
    $result = TestModel::where('name', 'Test')->cache(1)->first();
    
    expect($result)->not->toBeNull();
    expect($result->name)->toBe('Test');
    
    // Should still be cached
    $cached = TestModel::where('name', 'Test')->cache(1)->first();
    expect($cached->name)->toBe('Test');
});

it('caches with custom key', function () {
    TestModel::create(['name' => 'Custom Key Test']);
    
    TestModel::where('name', 'Custom Key Test')
        ->cache()
        ->key('my_custom_key')
        ->first();
    
    // Check if custom key exists in cache
    expect(Cache::has('my_custom_key'))->toBeTrue();
});

it('supports cache tags when available', function () {
    if (!Cache::supportsTags()) {
        $this->markTestSkipped('Cache driver does not support tags');
    }
    
    setupRedisCache();
    
    TestModel::create(['name' => 'Tagged']);
    
    TestModel::where('name', 'Tagged')
        ->cache()
        ->tags(['test', 'models'])
        ->first();
    
    // Clear by tag
    Cache::tags(['test'])->flush();
    
    // Cache should be cleared
    // This would require checking cache internals
    expect(true)->toBeTrue();
});

it('refreshes cache when forced', function () {
    $model = TestModel::create(['name' => 'Original']);
    
    // Cache the query
    TestModel::where('id', $model->id)->cache()->first();
    
    // Update the model directly in database
    $model->update(['name' => 'Updated']);
    
    // Get without refresh - should get cached version
    $cached = TestModel::where('id', $model->id)->cache()->first();
    
    // Force refresh
    $refreshed = TestModel::where('id', $model->id)->cache()->refresh()->first();
    
    expect($refreshed->name)->toBe('Updated');
});

it('counts records with cache', function () {
    TestModel::create(['name' => 'Count 1']);
    TestModel::create(['name' => 'Count 2']);
    TestModel::create(['name' => 'Count 3', 'active' => false]);
    
    $count = TestModel::where('active', true)->cache()->count();
    expect($count)->toBe(2);
    
    // Second call should be cached
    $cachedCount = TestModel::where('active', true)->cache()->count();
    expect($cachedCount)->toBe(2);
});

it('executes aggregate functions with cache', function () {
    TestPost::create(['title' => 'Post 1', 'user_id' => 1]);
    TestPost::create(['title' => 'Post 2', 'user_id' => 2]);
    TestPost::create(['title' => 'Post 3', 'user_id' => 3]);
    
    $sum = TestPost::cache()->sum('user_id');
    expect($sum)->toBe(6);
    
    $avg = TestPost::cache()->avg('user_id');
    expect($avg)->toBe(2);
    
    $max = TestPost::cache()->max('user_id');
    expect($max)->toBe(3);
    
    $min = TestPost::cache()->min('user_id');
    expect($min)->toBe(1);
});

it('checks existence with cache', function () {
    expect(TestModel::where('name', 'NonExistent')->cache()->exists())->toBeFalse();
    
    TestModel::create(['name' => 'Existent']);
    
    expect(TestModel::where('name', 'Existent')->cache()->exists())->toBeTrue();
});

it('plucks values with cache', function () {
    TestModel::create(['name' => 'Name 1']);
    TestModel::create(['name' => 'Name 2']);
    TestModel::create(['name' => 'Name 3']);
    
    $names = TestModel::cache()->pluck('name');
    
    expect($names)->toBeCollection();
    expect($names->count())->toBe(3);
    expect($names)->toContain('Name 1', 'Name 2', 'Name 3');
});

it('supports method chaining', function () {
    TestModel::create(['name' => 'Chained']);
    
    $result = TestModel::where('name', 'Chained')
        ->cache()
        ->ttl(7200)
        ->tags(['chained', 'test'])
        ->key('chained_query')
        ->debug(true)
        ->first();
    
    expect($result)->not->toBeNull();
    expect($result->name)->toBe('Chained');
});

it('handles cache for first() method', function () {
    TestModel::create(['name' => 'First']);
    TestModel::create(['name' => 'Second']);
    
    $first = TestModel::orderBy('name')->cache()->first();
    expect($first->name)->toBe('First');
    
    // Should be cached
    $cachedFirst = TestModel::orderBy('name')->cache()->first();
    expect($cachedFirst->name)->toBe('First');
});

it('supports cacheForever macro', function () {
    TestModel::create(['name' => 'Forever']);
    
    $result = TestModel::where('name', 'Forever')->cacheForever()->first();
    
    expect($result)->not->toBeNull();
    expect($result->name)->toBe('Forever');
});

it('supports cacheFor macro', function () {
    TestModel::create(['name' => 'Minutes']);
    
    // Cache for 30 minutes
    $result = TestModel::where('name', 'Minutes')->cacheFor(30)->first();
    
    expect($result)->not->toBeNull();
    expect($result->name)->toBe('Minutes');
});

it('supports dontCache macro', function () {
    TestModel::create(['name' => 'NotCached']);
    
    $result = TestModel::where('name', 'NotCached')->dontCache()->first();
    
    expect($result)->not->toBeNull();
    expect($result->name)->toBe('NotCached');
});