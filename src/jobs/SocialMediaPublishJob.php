<?php

namespace Jobs;

use Services\SocialMediaService;

/**
 * Social Media Publishing Job
 * Publishes scheduled social media posts
 */
class SocialMediaPublishJob
{
    private SocialMediaService $socialMediaService;

    public function __construct()
    {
        global $container;
        $this->socialMediaService = $container->get(SocialMediaService::class);
    }

    /**
     * Handle the job
     */
    public function handle(array $data): array
    {
        $postId = $data['post_id'];
        
        $result = $this->socialMediaService->publishPost($postId);
        
        return [
            'post_id' => $postId,
            'platform_post_id' => $result['post_id'] ?? null,
            'url' => $result['url'] ?? null,
            'published_at' => date('Y-m-d H:i:s')
        ];
    }
}