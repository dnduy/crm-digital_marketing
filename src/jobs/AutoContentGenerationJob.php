<?php

namespace Jobs;

use Services\AIContentService;
use Services\SocialMediaService;
use AI\ContentRequest;

/**
 * Auto Content Generation Job
 * Generates content using AI and schedules social media posts
 */
class AutoContentGenerationJob
{
    private AIContentService $contentService;
    private SocialMediaService $socialMediaService;

    public function __construct()
    {
        global $container;
        $this->contentService = $container->get(AIContentService::class);
        $this->socialMediaService = $container->get(SocialMediaService::class);
    }

    /**
     * Handle the job
     */
    public function handle(array $data): array
    {
        $request = new ContentRequest(
            $data['topic'] ?? '',
            $data['type'] ?? 'article',
            $data['keywords'] ?? [],
            $data['word_count'] ?? 500,
            $data['tone'] ?? 'professional',
            $data['language'] ?? 'vi',
            $data['requirements'] ?? []
        );

        // Generate content
        $content = $this->contentService->generateContent($request);

        $result = [
            'content_id' => $content['id'],
            'title' => $content['title'],
            'social_posts' => []
        ];

        // Generate social media posts if requested
        if (!empty($data['social_platforms'])) {
            $socialPosts = $this->socialMediaService->createPostsFromContent(
                $content['id'],
                $data['social_platforms'],
                [
                    'auto_publish' => $data['auto_publish'] ?? false,
                    'schedule_time' => $data['schedule_time'] ?? null
                ]
            );
            
            $result['social_posts'] = $socialPosts;
        }

        return $result;
    }
}