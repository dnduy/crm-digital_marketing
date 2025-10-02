<?php

namespace Services\Platforms;

use Core\Logger;

/**
 * Facebook Platform Handler
 * Handles Facebook posting and analytics
 */
class FacebookHandler implements PlatformHandlerInterface
{
    private array $config;
    private Logger $logger;
    private string $accessToken;
    private string $pageId;

    public function __construct(array $config, Logger $logger = null)
    {
        $this->config = $config;
        $this->logger = $logger ?: new Logger();
        $this->accessToken = $config['access_token'] ?? '';
        $this->pageId = $config['page_id'] ?? '';
    }

    /**
     * Publish post to Facebook
     */
    public function publish(array $post): array
    {
        if (!$this->accessToken || !$this->pageId) {
            throw new \Exception('Facebook credentials not configured');
        }

        $this->validatePost($post);

        $url = "https://graph.facebook.com/v18.0/{$this->pageId}/feed";
        
        $data = [
            'message' => $post['content'],
            'access_token' => $this->accessToken
        ];

        // Add link if present
        if (!empty($post['link'])) {
            $data['link'] = $post['link'];
        }

        // Add image if present
        if (!empty($post['image_url'])) {
            $data['picture'] = $post['image_url'];
        }

        $response = $this->makeRequest($url, $data);

        if (isset($response['error'])) {
            throw new \Exception('Facebook API Error: ' . $response['error']['message']);
        }

        return [
            'post_id' => $response['id'],
            'url' => "https://facebook.com/{$response['id']}",
            'platform_response' => $response
        ];
    }

    /**
     * Get post analytics
     */
    public function getAnalytics(string $postId): array
    {
        $url = "https://graph.facebook.com/v18.0/{$postId}/insights";
        
        $params = [
            'metric' => 'post_impressions,post_engaged_users,post_clicks,post_reactions_total',
            'access_token' => $this->accessToken
        ];

        $response = $this->makeRequest($url . '?' . http_build_query($params));

        if (isset($response['error'])) {
            $this->logger->error('Facebook Analytics Error', $response['error']);
            return [];
        }

        $analytics = [];
        foreach ($response['data'] as $metric) {
            $analytics[$metric['name']] = $metric['values'][0]['value'] ?? 0;
        }

        return [
            'impressions' => $analytics['post_impressions'] ?? 0,
            'engagement' => $analytics['post_engaged_users'] ?? 0,
            'clicks' => $analytics['post_clicks'] ?? 0,
            'reactions' => $analytics['post_reactions_total'] ?? 0,
            'platform' => 'facebook'
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

        $limits = $this->getLimits();
        
        if (strlen($post['content']) > $limits['char_limit']) {
            throw new \Exception("Post exceeds character limit of {$limits['char_limit']}");
        }

        return true;
    }

    /**
     * Get platform limits
     */
    public function getLimits(): array
    {
        return [
            'char_limit' => 63206,
            'hashtag_limit' => 30,
            'image_formats' => ['jpg', 'png', 'gif'],
            'video_formats' => ['mp4', 'mov', 'avi'],
            'max_file_size' => 4000000000 // 4GB
        ];
    }

    /**
     * Make HTTP request to Facebook API
     */
    private function makeRequest(string $url, array $data = null): array
    {
        $ch = curl_init();
        
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_USERAGENT => 'PHP-Facebook-Bot/1.0'
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
     * Get user pages
     */
    public function getUserPages(): array
    {
        $url = "https://graph.facebook.com/v18.0/me/accounts";
        
        $params = [
            'access_token' => $this->accessToken
        ];

        $response = $this->makeRequest($url . '?' . http_build_query($params));

        return $response['data'] ?? [];
    }

    /**
     * Upload image to Facebook
     */
    public function uploadImage(string $imagePath): string
    {
        $url = "https://graph.facebook.com/v18.0/{$this->pageId}/photos";
        
        $data = [
            'source' => new \CURLFile($imagePath),
            'published' => 'false', // Upload only, don't publish
            'access_token' => $this->accessToken
        ];

        $response = $this->makeRequest($url, $data);

        if (isset($response['error'])) {
            throw new \Exception('Image upload failed: ' . $response['error']['message']);
        }

        return $response['id'];
    }
}