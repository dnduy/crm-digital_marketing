<?php

namespace Services;

use AI\AIProviderInterface;
use Core\EventDispatcher;
use Core\Logger;
use Models\SocialMediaPost;

/**
 * Social Media Automation Service
 * Manages automated posting across social media platforms
 */
class SocialMediaService
{
    private AIProviderInterface $aiProvider;
    private SocialMediaPost $postModel;
    private EventDispatcher $events;
    private Logger $logger;
    private array $platforms;
    private array $config;

    public function __construct(
        AIProviderInterface $aiProvider,
        SocialMediaPost $postModel,
        EventDispatcher $events,
        Logger $logger,
        array $config = []
    ) {
        $this->aiProvider = $aiProvider;
        $this->postModel = $postModel;
        $this->events = $events;
        $this->logger = $logger;
        $this->config = $config;
        $this->initializePlatforms();
    }

    /**
     * Generate and schedule social media posts from content
     */
    public function createPostsFromContent(int $contentId, array $platforms = [], array $options = []): array
    {
        $content = $this->getContent($contentId);
        if (!$content) {
            throw new \Exception('Content not found');
        }

        $platforms = $platforms ?: array_keys($this->platforms);
        $posts = [];

        foreach ($platforms as $platform) {
            try {
                $post = $this->generatePostForPlatform($content, $platform, $options);
                $posts[$platform] = $post;

                $this->logger->info("Generated {$platform} post", [
                    'content_id' => $contentId,
                    'post_id' => $post['id']
                ]);

            } catch (\Exception $e) {
                $this->logger->error("Failed to generate {$platform} post", [
                    'content_id' => $contentId,
                    'error' => $e->getMessage()
                ]);
            }
        }

        $this->events->dispatch('social_media.posts_created', [
            'content_id' => $contentId,
            'posts' => $posts
        ]);

        return $posts;
    }

    /**
     * Generate post for specific platform
     */
    public function generatePostForPlatform(array $content, string $platform, array $options = []): array
    {
        $platformConfig = $this->platforms[$platform] ?? null;
        if (!$platformConfig) {
            throw new \Exception("Unsupported platform: {$platform}");
        }

        // Generate optimized content for platform
        $postContent = $this->aiProvider->generateSocialMediaPost(
            $content['content'],
            $platform,
            $platformConfig['char_limit']
        );

        // Extract hashtags and mentions
        $hashtags = $this->extractHashtags($postContent);
        $mentions = $this->extractMentions($postContent);

        // Create post record
        $postData = [
            'content_id' => $content['id'],
            'platform' => $platform,
            'content' => $postContent,
            'hashtags' => implode(',', $hashtags),
            'mentions' => implode(',', $mentions),
            'status' => $options['auto_publish'] ?? false ? 'scheduled' : 'draft',
            'scheduled_at' => $options['schedule_time'] ?? null,
            'created_at' => date('Y-m-d H:i:s')
        ];

        // Add platform-specific data
        $postData = array_merge($postData, $this->getPlatformSpecificData($platform, $content, $options));

        $postId = $this->postModel->create($postData);
        $postData['id'] = $postId;

        // Schedule publishing if needed
        if ($postData['status'] === 'scheduled') {
            $this->schedulePost($postId, $postData['scheduled_at']);
        }

        return $postData;
    }

    /**
     * Publish post to platform
     */
    public function publishPost(int $postId): array
    {
        $post = $this->postModel->find($postId);
        if (!$post) {
            throw new \Exception('Post not found');
        }

        $platform = $post['platform'];
        $platformHandler = $this->getPlatformHandler($platform);

        try {
            $result = $platformHandler->publish($post);
            
            $this->postModel->update($postId, [
                'status' => 'published',
                'published_at' => date('Y-m-d H:i:s'),
                'platform_post_id' => $result['post_id'] ?? null,
                'platform_url' => $result['url'] ?? null
            ]);

            $this->events->dispatch('social_media.post_published', [
                'post_id' => $postId,
                'platform' => $platform,
                'result' => $result
            ]);

            $this->logger->info("Published post to {$platform}", [
                'post_id' => $postId,
                'platform_post_id' => $result['post_id'] ?? null
            ]);

            return $result;

        } catch (\Exception $e) {
            $this->postModel->update($postId, [
                'status' => 'failed',
                'error_message' => $e->getMessage()
            ]);

            $this->logger->error("Failed to publish post to {$platform}", [
                'post_id' => $postId,
                'error' => $e->getMessage()
            ]);

            throw $e;
        }
    }

    /**
     * Schedule post for later publishing
     */
    public function schedulePost(int $postId, string $scheduledAt): void
    {
        // This would typically use a queue system like Redis or database jobs
        $this->events->dispatch('social_media.post_scheduled', [
            'post_id' => $postId,
            'scheduled_at' => $scheduledAt
        ]);
    }

    /**
     * Get analytics for published posts
     */
    public function getPostAnalytics(int $postId): array
    {
        $post = $this->postModel->find($postId);
        if (!$post || $post['status'] !== 'published') {
            return [];
        }

        $platformHandler = $this->getPlatformHandler($post['platform']);
        
        try {
            return $platformHandler->getAnalytics($post['platform_post_id']);
        } catch (\Exception $e) {
            $this->logger->error("Failed to get analytics for post", [
                'post_id' => $postId,
                'error' => $e->getMessage()
            ]);
            return [];
        }
    }

    /**
     * Auto-post campaign content
     */
    public function autoPostCampaign(int $campaignId, array $schedule = []): void
    {
        // Get campaign content
        $campaignContent = $this->getCampaignContent($campaignId);
        
        foreach ($campaignContent as $content) {
            $platforms = $this->getContentPlatforms($content);
            
            foreach ($platforms as $platform) {
                $scheduleTime = $this->calculateOptimalPostTime($platform, $schedule);
                
                $this->createPostsFromContent($content['id'], [$platform], [
                    'auto_publish' => true,
                    'schedule_time' => $scheduleTime
                ]);
            }
        }
    }

    /**
     * Initialize platform configurations
     */
    private function initializePlatforms(): void
    {
        $this->platforms = [
            'facebook' => [
                'char_limit' => 63206,
                'hashtag_limit' => 30,
                'supports_images' => true,
                'supports_videos' => true
            ],
            'instagram' => [
                'char_limit' => 2200,
                'hashtag_limit' => 30,
                'supports_images' => true,
                'supports_videos' => true
            ],
            'twitter' => [
                'char_limit' => 280,
                'hashtag_limit' => 10,
                'supports_images' => true,
                'supports_videos' => true
            ],
            'linkedin' => [
                'char_limit' => 3000,
                'hashtag_limit' => 10,
                'supports_images' => true,
                'supports_videos' => true
            ],
            'tiktok' => [
                'char_limit' => 150,
                'hashtag_limit' => 20,
                'supports_images' => false,
                'supports_videos' => true
            ]
        ];
    }

    /**
     * Get platform handler
     */
    private function getPlatformHandler(string $platform): PlatformHandlerInterface
    {
        $handlerClass = "\\Services\\Platforms\\" . ucfirst($platform) . "Handler";
        
        if (!class_exists($handlerClass)) {
            throw new \Exception("Platform handler not found: {$handlerClass}");
        }

        return new $handlerClass($this->config[$platform] ?? []);
    }

    /**
     * Extract hashtags from content
     */
    private function extractHashtags(string $content): array
    {
        preg_match_all('/#([a-zA-Z0-9_]+)/', $content, $matches);
        return $matches[1] ?? [];
    }

    /**
     * Extract mentions from content
     */
    private function extractMentions(string $content): array
    {
        preg_match_all('/@([a-zA-Z0-9_]+)/', $content, $matches);
        return $matches[1] ?? [];
    }

    /**
     * Get platform-specific data
     */
    private function getPlatformSpecificData(string $platform, array $content, array $options): array
    {
        $data = [];

        switch ($platform) {
            case 'instagram':
                $data['image_required'] = true;
                break;
            case 'tiktok':
                $data['video_required'] = true;
                break;
            case 'linkedin':
                $data['professional_tone'] = true;
                break;
        }

        return $data;
    }

    /**
     * Get content by ID
     */
    private function getContent(int $contentId): ?array
    {
        // This would typically use a ContentService or ContentModel
        global $db;
        $stmt = $db->prepare("SELECT * FROM content_pages WHERE id = ?");
        $stmt->execute([$contentId]);
        return $stmt->fetch(\PDO::FETCH_ASSOC) ?: null;
    }

    /**
     * Get campaign content
     */
    private function getCampaignContent(int $campaignId): array
    {
        global $db;
        $stmt = $db->prepare("SELECT * FROM content_pages WHERE campaign_id = ? AND status = 'published'");
        $stmt->execute([$campaignId]);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * Get platforms for content
     */
    private function getContentPlatforms(array $content): array
    {
        // Default platforms or from content settings
        return $content['target_platforms'] ? 
            explode(',', $content['target_platforms']) : 
            ['facebook', 'instagram', 'twitter'];
    }

    /**
     * Calculate optimal post time
     */
    private function calculateOptimalPostTime(string $platform, array $schedule): string
    {
        $optimalTimes = [
            'facebook' => ['09:00', '13:00', '18:00'],
            'instagram' => ['11:00', '14:00', '17:00'],
            'twitter' => ['08:00', '12:00', '17:00', '19:00'],
            'linkedin' => ['08:00', '10:00', '12:00', '17:00'],
            'tiktok' => ['18:00', '20:00', '21:00']
        ];

        $times = $optimalTimes[$platform] ?? ['12:00'];
        $selectedTime = $times[array_rand($times)];

        // Add random days to spread posts
        $days = rand(0, 7);
        $date = date('Y-m-d', strtotime("+{$days} days"));

        return "{$date} {$selectedTime}:00";
    }
}