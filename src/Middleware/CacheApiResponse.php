<?php

namespace Fxcjahid\LaravelEloquentCacheMagic\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;

/**
 * Cache API Response Middleware
 * 
 * Automatically cache API responses for improved performance.
 * 
 * @package Fxcjahid\LaravelEloquentCacheMagic\Middleware
 */
class CacheApiResponse
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     * @param  int|null  $ttl  Cache duration in seconds
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function handle(Request $request, Closure $next, ?int $ttl = null): Response
    {
        // Skip caching if disabled
        if (!config('cache-magic.middleware.enabled', false)) {
            return $next($request);
        }

        // Only cache specific HTTP methods (typically GET)
        $allowedMethods = config('cache-magic.middleware.methods', ['GET']);
        if (!in_array($request->method(), $allowedMethods)) {
            return $next($request);
        }

        // Skip caching if user is authenticated (optional)
        if ($this->shouldSkipForAuthenticatedUser($request)) {
            return $next($request);
        }

        // Generate cache key
        $cacheKey = $this->generateCacheKey($request);
        
        // Check if response is cached
        if ($cachedResponse = $this->getCachedResponse($cacheKey)) {
            return $this->buildResponse($cachedResponse, true);
        }

        // Execute request
        $response = $next($request);

        // Only cache successful responses
        if ($this->shouldCacheResponse($response)) {
            $this->cacheResponse($cacheKey, $response, $ttl);
        }

        return $response;
    }

    /**
     * Generate cache key for the request
     */
    protected function generateCacheKey(Request $request): string
    {
        $url = $request->url();
        $queryParams = $this->getQueryParams($request);
        $method = $request->method();
        
        // Include user context if needed
        $userContext = '';
        if ($user = $request->user()) {
            $userContext = ':user:' . $user->id;
        }
        
        // Include API version if present
        $version = $request->header('API-Version', 'v1');
        
        // Build cache key
        $key = 'api:' . $version . ':' . $method . ':' . md5($url . serialize($queryParams)) . $userContext;
        
        return $key;
    }

    /**
     * Get query parameters excluding sensitive ones
     */
    protected function getQueryParams(Request $request): array
    {
        $params = $request->query();
        $excludeParams = config('cache-magic.middleware.exclude_params', [
            'token',
            'api_key',
            '_',
            'timestamp',
        ]);
        
        foreach ($excludeParams as $param) {
            unset($params[$param]);
        }
        
        // Sort params for consistent cache key
        ksort($params);
        
        return $params;
    }

    /**
     * Check if response should be cached
     */
    protected function shouldCacheResponse(Response $response): bool
    {
        // Only cache successful responses (2xx)
        if ($response->getStatusCode() < 200 || $response->getStatusCode() >= 300) {
            return false;
        }
        
        // Check for no-cache headers
        if ($response->headers->hasCacheControlDirective('no-cache') ||
            $response->headers->hasCacheControlDirective('no-store')) {
            return false;
        }
        
        // Check response size (don't cache huge responses)
        $maxSize = config('cache-magic.middleware.max_response_size', 1024 * 1024); // 1MB default
        if (strlen($response->getContent()) > $maxSize) {
            return false;
        }
        
        return true;
    }

    /**
     * Cache the response
     */
    protected function cacheResponse(string $key, Response $response, ?int $ttl = null): void
    {
        $ttl = $ttl ?? config('cache-magic.middleware.ttl', 300); // 5 minutes default
        
        $data = [
            'content' => $response->getContent(),
            'status' => $response->getStatusCode(),
            'headers' => $this->getCacheableHeaders($response),
            'cached_at' => now()->toIso8601String(),
        ];
        
        // Use tags if supported
        if (Cache::supportsTags()) {
            $tags = $this->getCacheTags();
            Cache::tags($tags)->put($key, $data, $ttl);
        } else {
            Cache::put($key, $data, $ttl);
        }
        
        // Track statistics
        if (config('cache-magic.statistics.enabled', true)) {
            app(\Fxcjahid\LaravelEloquentCacheMagic\Monitoring\CacheStatistics::class)
                ->recordWrite($key);
        }
    }

    /**
     * Get cached response
     */
    protected function getCachedResponse(string $key): ?array
    {
        $data = null;
        
        if (Cache::supportsTags()) {
            $tags = $this->getCacheTags();
            $data = Cache::tags($tags)->get($key);
        } else {
            $data = Cache::get($key);
        }
        
        // Track statistics
        if (config('cache-magic.statistics.enabled', true)) {
            $stats = app(\Fxcjahid\LaravelEloquentCacheMagic\Monitoring\CacheStatistics::class);
            if ($data) {
                $stats->recordHit($key);
            } else {
                $stats->recordMiss($key);
            }
        }
        
        return $data;
    }

    /**
     * Build response from cached data
     */
    protected function buildResponse(array $data, bool $fromCache = false): Response
    {
        $response = response($data['content'], $data['status']);
        
        // Set headers
        foreach ($data['headers'] as $key => $value) {
            $response->header($key, $value);
        }
        
        // Add cache headers
        if ($fromCache) {
            $response->header('X-Cache', 'HIT');
            $response->header('X-Cache-Time', $data['cached_at'] ?? '');
        } else {
            $response->header('X-Cache', 'MISS');
        }
        
        return $response;
    }

    /**
     * Get headers that should be cached
     */
    protected function getCacheableHeaders(Response $response): array
    {
        $headers = [];
        $cacheableHeaders = [
            'Content-Type',
            'Content-Encoding',
            'Content-Language',
            'ETag',
            'Last-Modified',
        ];
        
        foreach ($cacheableHeaders as $header) {
            if ($response->headers->has($header)) {
                $headers[$header] = $response->headers->get($header);
            }
        }
        
        return $headers;
    }

    /**
     * Get cache tags for the middleware
     */
    protected function getCacheTags(): array
    {
        return config('cache-magic.middleware.tags', ['api', 'responses']);
    }

    /**
     * Check if caching should be skipped for authenticated users
     */
    protected function shouldSkipForAuthenticatedUser(Request $request): bool
    {
        if (!config('cache-magic.middleware.cache_authenticated', false)) {
            return $request->user() !== null;
        }
        
        return false;
    }
}