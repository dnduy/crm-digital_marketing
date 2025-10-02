<?php

namespace Services\Platforms;

/**
 * Platform Handler Interface
 * Contract for social media platform handlers
 */
interface PlatformHandlerInterface
{
    /**
     * Publish post to platform
     */
    public function publish(array $post): array;

    /**
     * Get post analytics
     */
    public function getAnalytics(string $postId): array;

    /**
     * Validate post data
     */
    public function validatePost(array $post): bool;

    /**
     * Get platform limits
     */
    public function getLimits(): array;
}