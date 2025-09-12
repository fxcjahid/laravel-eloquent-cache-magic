<?php

namespace Fxcjahid\LaravelEloquentCacheMagic\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Fxcjahid\LaravelEloquentCacheMagic\CacheQueryBuilder;

class RefreshCacheJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;
    
    protected $query;
    protected array $options;
    
    /**
     * Create a new job instance.
     */
    public function __construct($query, array $options = [])
    {
        $this->query = $query;
        $this->options = $options;
        
        // Set queue and connection from config
        $this->onQueue(config('cache-magic.async.queue', 'default'));
        
        if ($connection = config('cache-magic.async.connection')) {
            $this->onConnection($connection);
        }
    }
    
    /**
     * Execute the job.
     */
    public function handle(): void
    {
        // Force refresh the cache
        $this->options['refresh'] = true;
        
        $cacheBuilder = new CacheQueryBuilder($this->query, $this->options);
        
        // Execute the query to refresh cache
        $cacheBuilder->get();
    }
}