<?php

namespace Fxcjahid\LaravelEloquentCacheMagic\Concerns;

use Illuminate\Support\Facades\Auth;

/**
 * Shared trait for consistent cache tag generation
 */
trait ManagesCacheTags
{
    /**
     * Build complete cache tags array with all automatic tags
     *
     * @param array $baseTags Base tags from model or options
     * @param object|null $model The model instance
     * @return array Complete tags array
     */
    protected function buildCacheTags(array $baseTags = [], $model = null): array
    {
        $tags = $baseTags;

        if ($model) {
            $tags[] = 'model:' . strtolower(class_basename($model));
        }

        if ($globalTags = config('cache-magic.global_tags', [])) {
            $tags = array_merge($tags, $globalTags);
        }

        if (config('cache-magic.auto_user_tags.enabled', true)) {
            $tags[] = $this->getUserCacheTag();
        }

        return array_unique($tags);
    }

    /**
     * Get user or guest cache tag
     *
     * @return string
     */
    protected function getUserCacheTag(): string
    {
        try {
            if (Auth::check()) {
                return 'user:' . Auth::id();
            }
        } catch (\Exception $e) {
            // Auth not available (during bootstrap), continue to guest
        }

        try {
            $fallback = config('cache-magic.auto_user_tags.guest_fallback', 'session');
            $guestId = $this->getGuestIdentifier($fallback);
            return "guest:$guestId";
        } catch (\Exception $e) {
            // If all fails, return a default guest tag
            return 'guest:' . uniqid();
        }
    }

    /**
     * Get guest identifier based on fallback strategy
     *
     * @param string $fallback
     * @return string
     */
    protected function getGuestIdentifier(string $fallback): string
    {
        try {
            switch ($fallback) {
                case 'session':
                    return session()->getId() ?: uniqid('no-session-');

                case 'ip':
                    return md5(request()->ip() ?: 'no-ip');

                case 'unique':
                default:
                    return uniqid('guest-');
            }
        } catch (\Exception $e) {
            return uniqid('guest-');
        }
    }
}