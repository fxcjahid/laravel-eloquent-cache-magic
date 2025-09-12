<?php

namespace Fxcjahid\LaravelEloquentCacheMagic\Tests;

use Orchestra\Testbench\TestCase as Orchestra;
use Fxcjahid\LaravelEloquentCacheMagic\CacheMagicServiceProvider;
use Illuminate\Database\Schema\Blueprint;

class TestCase extends Orchestra
{
    protected function setUp(): void
    {
        parent::setUp();
        
        $this->setUpDatabase();
    }
    
    protected function getPackageProviders($app)
    {
        return [
            CacheMagicServiceProvider::class,
        ];
    }
    
    protected function getEnvironmentSetUp($app)
    {
        // Setup default database
        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);
        
        // Setup cache
        $app['config']->set('cache.default', 'array');
        $app['config']->set('cache-magic.enabled', true);
        $app['config']->set('cache-magic.default_ttl', 3600);
    }
    
    protected function setUpDatabase()
    {
        // Create test tables
        $this->app['db']->connection()->getSchemaBuilder()->create('test_models', function (Blueprint $table) {
            $table->increments('id');
            $table->string('name');
            $table->boolean('active')->default(true);
            $table->timestamps();
        });
        
        $this->app['db']->connection()->getSchemaBuilder()->create('test_posts', function (Blueprint $table) {
            $table->increments('id');
            $table->string('title');
            $table->text('content');
            $table->integer('user_id')->nullable();
            $table->string('status')->default('draft');
            $table->timestamps();
        });
    }
}