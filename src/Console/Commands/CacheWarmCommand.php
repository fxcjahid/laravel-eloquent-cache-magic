<?php

namespace Fxcjahid\LaravelEloquentCacheMagic\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

class CacheWarmCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'cache-magic:warm 
                            {--model=* : Specific models to warm}
                            {--config : Use queries from config file}
                            {--force : Force refresh even if cached}
                            {--parallel : Run warming in parallel}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Warm up cache by pre-loading configured queries';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle(): int
    {
        $this->info('🔥 Starting cache warming...');
        $startTime = microtime(true);
        
        // Get queries to warm
        $queries = $this->getQueriesToWarm();
        
        if (empty($queries)) {
            $this->warn('No queries configured for warming.');
            $this->info('Configure queries in config/cache-magic.php under "warming.queries"');
            return 1;
        }

        $total = count($queries);
        $this->info("Found {$total} queries to warm up.");
        
        // Create progress bar
        $bar = $this->output->createProgressBar($total);
        $bar->start();
        
        $successful = 0;
        $failed = 0;
        $errors = [];

        foreach ($queries as $index => $query) {
            try {
                $this->warmQuery($query);
                $successful++;
            } catch (\Exception $e) {
                $failed++;
                $errors[] = [
                    'query' => $query['description'] ?? "Query #{$index}",
                    'error' => $e->getMessage()
                ];
            }
            
            $bar->advance();
        }
        
        $bar->finish();
        $this->newLine(2);
        
        // Show results
        $elapsed = round(microtime(true) - $startTime, 2);
        
        $this->info("✅ Cache warming completed in {$elapsed} seconds!");
        $this->table(
            ['Metric', 'Value'],
            [
                ['Total Queries', $total],
                ['Successful', $successful],
                ['Failed', $failed],
                ['Time Elapsed', "{$elapsed}s"],
                ['Cache Driver', config('cache.default')],
            ]
        );
        
        // Show errors if any
        if (!empty($errors)) {
            $this->newLine();
            $this->error('⚠️ Errors encountered:');
            $this->table(['Query', 'Error'], $errors);
        }
        
        return $failed > 0 ? 1 : 0;
    }

    /**
     * Get queries to warm from configuration
     */
    protected function getQueriesToWarm(): array
    {
        $queries = [];
        
        // Get from config if flag is set
        if ($this->option('config')) {
            $queries = config('cache-magic.warming.queries', []);
        }
        
        // Get specific models if provided
        if ($models = $this->option('model')) {
            foreach ($models as $model) {
                $queries[] = $this->buildModelQuery($model);
            }
        }
        
        // If no queries yet, get default from config
        if (empty($queries)) {
            $queries = config('cache-magic.warming.queries', []);
        }
        
        return $queries;
    }

    /**
     * Build query configuration for a model
     */
    protected function buildModelQuery(string $model): array
    {
        if (!class_exists($model)) {
            $model = "App\\Models\\{$model}";
        }
        
        return [
            'model' => $model,
            'method' => 'all',
            'cache_method' => 'get',
            'ttl' => 3600,
            'tags' => ['model:' . strtolower(class_basename($model))],
            'description' => "All {$model} records",
        ];
    }

    /**
     * Warm a specific query
     */
    protected function warmQuery(array $config): void
    {
        $model = $config['model'] ?? null;
        
        if (!$model || !class_exists($model)) {
            throw new \InvalidArgumentException("Invalid model: {$model}");
        }
        
        // Build the query
        $query = $model::query();
        
        // Apply query method if specified
        if (isset($config['method'])) {
            $method = $config['method'];
            $args = $config['args'] ?? [];
            
            if (is_callable([$query, $method])) {
                $query = $query->$method(...$args);
            }
        }
        
        // Apply scopes if specified
        if (isset($config['scopes'])) {
            foreach ($config['scopes'] as $scope => $scopeArgs) {
                $query = $query->$scope(...($scopeArgs ?? []));
            }
        }
        
        // Apply relationships if specified
        if (isset($config['with'])) {
            $query = $query->with($config['with']);
        }
        
        // Set up cache configuration
        $cacheOptions = [];
        
        if (isset($config['ttl'])) {
            $cacheOptions['ttl'] = $config['ttl'];
        }
        
        if (isset($config['tags'])) {
            $cacheOptions['tags'] = $config['tags'];
        }
        
        if (isset($config['key'])) {
            $cacheOptions['key'] = $config['key'];
        }
        
        // Force refresh if option is set
        if ($this->option('force')) {
            $cacheOptions['refresh'] = true;
        }
        
        // Apply cache and execute
        $cacheMethod = $config['cache_method'] ?? 'get';
        $query->cache($cacheOptions)->$cacheMethod();
        
        // Log if verbose
        if ($this->output->isVerbose()) {
            $description = $config['description'] ?? "Query for {$model}";
            $this->line(" - Warmed: {$description}");
        }
    }
}

/**
 * Example configuration in config/cache-magic.php:
 * 
 * 'warming' => [
 *     'enabled' => true,
 *     'schedule' => '0 * * * *', // Hourly
 *     'queries' => [
 *         [
 *             'model' => \App\Models\Product::class,
 *             'method' => 'where',
 *             'args' => ['featured', true],
 *             'with' => ['images', 'reviews'],
 *             'cache_method' => 'get',
 *             'ttl' => 7200,
 *             'tags' => ['products', 'featured'],
 *             'description' => 'Featured products with images and reviews',
 *         ],
 *         [
 *             'model' => \App\Models\User::class,
 *             'method' => 'where',
 *             'args' => ['active', true],
 *             'cache_method' => 'count',
 *             'ttl' => 3600,
 *             'tags' => ['users', 'stats'],
 *             'description' => 'Active users count',
 *         ],
 *     ],
 * ],
 */