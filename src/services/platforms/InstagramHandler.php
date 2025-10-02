<?php

namespace Services\Platforms;

use Core\Logger;

/**
 * Instagram Platform Handler
 * Handles Instagram posting via Facebook Graph API
 */
class InstagramHandler implements PlatformHandlerInterface
{
    private array $config;
    private Logger $logger;
    private string $accessToken;
    private string $instagramAccountId;

    public function __construct(array $config, Logger $logger = null)
    {
        $this->config = $config;
        $this->logger = $logger ?: new Logger();
        $this->accessToken = $config['access_token'] ?? '';
        $this->instagramAccountId = $config['instagram_account_id'] ?? '';
    }

    /**
     * Publish post to Instagram
     */
    public function publish(array $post): array
    {
        if (!$this->accessToken || !$this->instagramAccountId) {
            throw new \Exception('Instagram credentials not configured');
        }

        $this->validatePost($post);

        // Instagram requires images, so we need to handle image upload first
        if (empty($post['image_url'])) {
            throw new \Exception('Instagram posts require an image');
        }

        // Step 1: Create media container
        $containerId = $this->createMediaContainer($post);
        
        // Step 2: Publish the container
        $publishResponse = $this->publishMediaContainer($containerId);

        return [
            'post_id' => $publishResponse['id'],
            'url' => "https://instagram.com/p/{$this->getMediaCode($publishResponse['id'])}",
            'platform_response' => $publishResponse
        ];
    }

    /**
     * Get post analytics
     */
    public function getAnalytics(string $postId): array
    {
        $url = "https://graph.facebook.com/v18.0/{$postId}/insights";
        
        $params = [
            'metric' => 'impressions,reach,engagement,saves,profile_visits',
            'access_token' => $this->accessToken
        ];

        $response = $this->makeRequest($url . '?' . http_build_query($params));

        if (isset($response['error'])) {
            $this->logger->error('Instagram Analytics Error', $response['error']);
            return [];
        }

        $analytics = [];
        foreach ($response['data'] as $metric) {
            $analytics[$metric['name']] = $metric['values'][0]['value'] ?? 0;
        }

        return [
            'impressions' => $analytics['impressions'] ?? 0,
            'reach' => $analytics['reach'] ?? 0,
            'engagement' => $analytics['engagement'] ?? 0,
            'saves' => $analytics['saves'] ?? 0,
            'profile_visits' => $analytics['profile_visits'] ?? 0,
            'platform' => 'instagram'
        ];
    }

    /**
     * Validate post data
     */
    public function validatePost(array $post): bool
    {
        if (empty($post['content'])) {
            throw new \Exception('Post content is required');
        }

        if (empty($post['image_url'])) {
            throw new \Exception('Instagram posts require an image');
        }

        $limits = $this->getLimits();
        
        if (strlen($post['content']) > $limits['char_limit']) {
            throw new \Exception("Post exceeds character limit of {$limits['char_limit']}");
        }

        // Validate hashtags
        $hashtags = $this->extractHashtags($post['content']);
        if (count($hashtags) > $limits['hashtag_limit']) {
            throw new \Exception("Post exceeds hashtag limit of {$limits['hashtag_limit']}");
        }

        return true;
    }

    /**
     * Get platform limits
     */
    public function getLimits(): array
    {
        return [
            'char_limit' => 2200,
            'hashtag_limit' => 30,
            'image_formats' => ['jpg', 'png'],
            'video_formats' => ['mp4', 'mov'],
            'max_file_size' => 100000000, // 100MB
            'aspect_ratios' => ['1:1', '4:5', '16:9']
        ];
    }

    /**
     * Create media container
     */
    private function createMediaContainer(array $post): string
    {
        $url = "https://graph.facebook.com/v18.0/{$this->instagramAccountId}/media";
        
        $data = [
            'image_url' => $post['image_url'],
            'caption' => $post['content'],
            'access_token' => $this->accessToken
        ];

        // Handle video posts
        if (!empty($post['video_url'])) {
            $data['media_type'] = 'VIDEO';
            $data['video_url'] = $post['video_url'];
            unset($data['image_url']);
        }

        $response = $this->makeRequest($url, $data);

        if (isset($response['error'])) {
            throw new \Exception('Failed to create media container: ' . $response['error']['message']);
        }

        return $response['id'];
    }

    /**
     * Publish media container
     */
    private function publishMediaContainer(string $containerId): array
    {
        $url = "https://graph.facebook.com/v18.0/{$this->instagramAccountId}/media_publish";
        
        $data = [
            'creation_id' => $containerId,
            'access_token' => $this->accessToken
        ];

        $response = $this->makeRequest($url, $data);

        if (isset($response['error'])) {
            throw new \Exception('Failed to publish media: ' . $response['error']['message']);
        }

        return $response;
    }

    /**
     * Make HTTP request to Instagram API
     */
    private function makeRequest(string $url, array $data = null): array
    {
        $ch = curl_init();
        
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_USERAGENT => 'PHP-Instagram-Bot/1.0'
        ]);

        if ($data) {
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
        }

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        
        if (curl_error($ch)) {
            throw new \Exception('cURL Error: ' . curl_error($ch));
        }
        
        curl_close($ch);

        $decoded = json_decode($response, true);
        
        if ($httpCode >= 400) {
            throw new \Exception("HTTP Error {$httpCode}: " . ($decoded['error']['message'] ?? $response));
        }

        return $decoded ?: [];
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
     * Get media code from Instagram media ID
     */
    private function getMediaCode(string $mediaId): string
    {
        // Convert Instagram media ID to shortcode
        // This is a simplified version - actual implementation may vary
        return base_convert($mediaId, 10, 36);
    }

    /**
     * Get Instagram account info
     */
    public function getAccountInfo(): array
    {
        $url = "https://graph.facebook.com/v18.0/{$this->instagramAccountId}";
        
        $params = [
            'fields' => 'id,username,account_type,media_count,followers_count',
            'access_token' => $this->accessToken
        ];

        $response = $this->makeRequest($url . '?' . http_build_query($params));

        return $response;
    }

    /**
     * Create carousel post (multiple images)
     */
    public function createCarouselPost(array $images, string $caption): array
    {
        $children = [];
        
        // Create media containers for each image
        foreach ($images as $image) {
            $url = "https://graph.facebook.com/v18.0/{$this->instagramAccountId}/media";
            
            $data = [
                'image_url' => $image,
                'is_carousel_item' => 'true',
                'access_token' => $this->accessToken
            ];

            $response = $this->makeRequest($url, $data);
            
            if (isset($response['id'])) {
                $children[] = $response['id'];
            }
        }

        if (empty($children)) {
            throw new \Exception('Failed to create carousel items');
        }

        // Create carousel container
        $url = "https://graph.facebook.com/v18.0/{$this->instagramAccountId}/media";
        
        $data = [
            'media_type' => 'CAROUSEL',
            'children' => implode(',', $children),
            'caption' => $caption,
            'access_token' => $this->accessToken
        ];

        $carouselResponse = $this->makeRequest($url, $data);

        if (isset($carouselResponse['error'])) {
            throw new \Exception('Failed to create carousel: ' . $carouselResponse['error']['message']);
        }

        // Publish carousel
        return $this->publishMediaContainer($carouselResponse['id']);
    }
}