<?php
// ==========================
// FILE: /lib/social/FacebookPlatform.php
// Facebook Platform Handler with Graph API integration
// ==========================

require_once __DIR__ . '/SocialMediaPlatformInterface.php';

class FacebookPlatform extends AbstractSocialMediaPlatform {
    private $appId;
    private $appSecret;
    private $pageId;
    private $userId;
    
    protected function initializePlatform(): void {
        $this->platformName = 'facebook';
        $this->apiBaseUrl = 'https://graph.facebook.com/v18.0/';
        $this->rateLimits = [
            'posts_per_hour' => 200,
            'posts_per_day' => 2000
        ];
    }
    
    public function authenticate(array $credentials): bool {
        try {
            $this->appId = $credentials['app_id'] ?? '';
            $this->appSecret = $credentials['app_secret'] ?? '';
            $this->accessToken = $credentials['access_token'] ?? '';
            $this->pageId = $credentials['page_id'] ?? null;
            
            if ($this->validateToken()) {
                // Get user ID
                $userInfo = $this->makeApiRequest('me?fields=id,name');
                $this->userId = $userInfo['id'] ?? null;
                return true;
            }
            
            return false;
        } catch (Exception $e) {
            $this->logActivity('authentication_failed', ['error' => $e->getMessage()]);
            return false;
        }
    }
    
    public function createPost(array $postData): array {
        try {
            if (!$this->checkRateLimit()) {
                throw new Exception('Rate limit exceeded');
            }
            
            $content = $this->sanitizeContent($postData['content'] ?? '');
            
            // Determine posting target (page or user)
            $targetId = $this->pageId ?: $this->userId;
            if (!$targetId) {
                throw new Exception('No valid posting target (page or user)');
            }
            
            $postParams = ['message' => $content];
            
            // Handle link sharing
            if (!empty($postData['link_url'])) {
                $postParams['link'] = $postData['link_url'];
                if (!empty($postData['link_name'])) {
                    $postParams['name'] = $postData['link_name'];
                }
                if (!empty($postData['link_description'])) {
                    $postParams['description'] = $postData['link_description'];
                }
            }
            
            // Handle photo posting
            if (!empty($postData['media_urls']) && empty($postData['link_url'])) {
                return $this->createPhotoPost($targetId, $content, $postData['media_urls']);
            }
            
            // Handle video posting
            if (!empty($postData['video_url'])) {
                return $this->createVideoPost($targetId, $content, $postData['video_url']);
            }
            
            // Create text or link post
            $response = $this->makeApiRequest("{$targetId}/feed", $postParams, 'POST');
            
            $this->logActivity('post_created', [
                'post_id' => $response['id'] ?? null,
                'target_id' => $targetId,
                'content_length' => strlen($content)
            ]);
            
            return [
                'success' => true,
                'post_id' => $response['id'] ?? null,
                'platform_response' => $response
            ];
            
        } catch (Exception $e) {
            return $this->handleApiError($e);
        }
    }
    
    public function schedulePost(array $postData, DateTime $scheduleTime): array {
        try {
            $content = $this->sanitizeContent($postData['content'] ?? '');
            $targetId = $this->pageId ?: $this->userId;
            
            if (!$this->pageId) {
                throw new Exception('Scheduled posting requires a Facebook Page');
            }
            
            $publishTime = $scheduleTime->getTimestamp();
            $postParams = [
                'message' => $content,
                'published' => false,
                'scheduled_publish_time' => $publishTime
            ];
            
            // Handle media for scheduled posts
            if (!empty($postData['media_urls'])) {
                $mediaUrl = $postData['media_urls'][0];
                $postParams['url'] = $mediaUrl;
            }
            
            $response = $this->makeApiRequest("{$targetId}/feed", $postParams, 'POST');
            
            $this->logActivity('post_scheduled', [
                'post_id' => $response['id'] ?? null,
                'scheduled_time' => $scheduleTime->format('Y-m-d H:i:s')
            ]);
            
            return [
                'success' => true,
                'post_id' => $response['id'] ?? null,
                'scheduled_time' => $scheduleTime->format('Y-m-d H:i:s'),
                'platform_response' => $response
            ];
            
        } catch (Exception $e) {
            return $this->handleApiError($e);
        }
    }
    
    public function getAccountInfo(): array {
        try {
            if ($this->pageId) {
                // Get Page information
                $response = $this->makeApiRequest("{$this->pageId}?fields=id,name,username,about,category,fan_count,followers_count,picture,website,link");
                
                return [
                    'success' => true,
                    'account_id' => $response['id'] ?? null,
                    'account_type' => 'page',
                    'username' => $response['username'] ?? null,
                    'display_name' => $response['name'] ?? null,
                    'description' => $response['about'] ?? null,
                    'category' => $response['category'] ?? null,
                    'followers_count' => $response['fan_count'] ?? 0,
                    'website' => $response['website'] ?? null,
                    'profile_url' => $response['link'] ?? null,
                    'profile_image_url' => $response['picture']['data']['url'] ?? null,
                    'raw_data' => $response
                ];
            } else {
                // Get User information
                $response = $this->makeApiRequest('me?fields=id,name,email,picture');
                
                return [
                    'success' => true,
                    'account_id' => $response['id'] ?? null,
                    'account_type' => 'user',
                    'username' => null,
                    'display_name' => $response['name'] ?? null,
                    'email' => $response['email'] ?? null,
                    'profile_image_url' => $response['picture']['data']['url'] ?? null,
                    'raw_data' => $response
                ];
            }
            
        } catch (Exception $e) {
            return $this->handleApiError($e);
        }
    }
    
    public function getAnalytics(array $options = []): array {
        try {
            if (!$this->pageId) {
                throw new Exception('Analytics require Facebook Page access');
            }
            
            $since = $options['since'] ?? date('Y-m-d', strtotime('-30 days'));
            $until = $options['until'] ?? date('Y-m-d');
            
            // Get page insights
            $insights = $this->makeApiRequest("{$this->pageId}/insights?metric=page_impressions,page_reach,page_engaged_users,page_post_engagements&since={$since}&until={$until}&period=day");
            
            // Get recent posts with engagement
            $posts = $this->makeApiRequest("{$this->pageId}/posts?fields=id,message,created_time,likes.summary(true),comments.summary(true),shares&limit=25");
            
            $analytics = [];
            foreach ($posts['data'] ?? [] as $post) {
                $analytics[] = [
                    'post_id' => $post['id'],
                    'created_time' => $post['created_time'],
                    'message' => substr($post['message'] ?? '', 0, 100),
                    'likes_count' => $post['likes']['summary']['total_count'] ?? 0,
                    'comments_count' => $post['comments']['summary']['total_count'] ?? 0,
                    'shares_count' => $post['shares']['count'] ?? 0,
                    'engagement_score' => $this->calculateEngagementScore($post)
                ];
            }
            
            return [
                'success' => true,
                'page_insights' => $insights['data'] ?? [],
                'posts_analytics' => $analytics,
                'summary' => [
                    'total_posts' => count($analytics),
                    'avg_engagement' => array_sum(array_column($analytics, 'engagement_score')) / max(count($analytics), 1)
                ]
            ];
            
        } catch (Exception $e) {
            return $this->handleApiError($e);
        }
    }
    
    public function getAudienceInsights(): array {
        try {
            if (!$this->pageId) {
                throw new Exception('Audience insights require Facebook Page access');
            }
            
            // Get page fan demographics
            $demographics = $this->makeApiRequest("{$this->pageId}/insights?metric=page_fans_gender_age,page_fans_country,page_fans_city&period=lifetime");
            
            $accountInfo = $this->getAccountInfo();
            
            return [
                'success' => true,
                'total_followers' => $accountInfo['followers_count'] ?? 0,
                'demographics' => $demographics['data'] ?? [],
                'engagement_insights' => 'Available in Facebook Page insights',
                'reach_insights' => 'Available in Facebook Page insights'
            ];
            
        } catch (Exception $e) {
            return $this->handleApiError($e);
        }
    }
    
    public function uploadMedia(string $filePath, string $type = 'image'): array {
        try {
            if (!file_exists($filePath)) {
                throw new Exception('Media file not found');
            }
            
            $targetId = $this->pageId ?: $this->userId;
            
            if ($type === 'video') {
                // Upload video
                $postData = [
                    'source' => new CURLFile($filePath),
                    'access_token' => $this->accessToken
                ];
                
                $response = $this->uploadWithCurl("{$targetId}/videos", $postData);
            } else {
                // Upload photo
                $postData = [
                    'source' => new CURLFile($filePath),
                    'published' => false, // Upload unpublished for later use
                    'access_token' => $this->accessToken
                ];
                
                $response = $this->uploadWithCurl("{$targetId}/photos", $postData);
            }
            
            return [
                'success' => true,
                'media_id' => $response['id'] ?? null,
                'media_url' => $response['source'] ?? null
            ];
            
        } catch (Exception $e) {
            return $this->handleApiError($e);
        }
    }
    
    public function deletePost(string $postId): bool {
        try {
            $response = $this->makeApiRequest($postId, [], 'DELETE');
            
            $this->logActivity('post_deleted', ['post_id' => $postId]);
            
            return isset($response['success']) && $response['success'] === true;
            
        } catch (Exception $e) {
            $this->logActivity('delete_failed', ['post_id' => $postId, 'error' => $e->getMessage()]);
            return false;
        }
    }
    
    public function getPostingGuidelines(): array {
        return [
            'max_content_length' => 63206, // Very high limit
            'max_media_count' => 10,
            'supported_media_types' => ['image/jpeg', 'image/png', 'image/gif', 'video/mp4', 'video/avi'],
            'max_media_size' => '4GB for videos, 25MB for photos',
            'hashtag_recommendations' => 'Use 1-5 relevant hashtags',
            'posting_frequency' => 'Max 200 posts per hour, 2000 per day',
            'best_posting_times' => [
                'Tuesday-Thursday: 13:00-16:00',
                'Saturday-Sunday: 12:00-13:00'
            ],
            'engagement_tips' => [
                'Ask questions to encourage comments',
                'Use high-quality visuals',
                'Post consistently',
                'Respond to comments quickly',
                'Use Facebook-specific features (polls, events, etc.)'
            ],
            'scheduling_support' => true,
            'page_vs_profile' => 'Pages have more features and analytics than personal profiles'
        ];
    }
    
    public function supportsContentType(string $type): bool {
        $supportedTypes = ['text', 'image', 'video', 'link', 'event', 'poll'];
        return in_array($type, $supportedTypes);
    }
    
    public function validateToken(): bool {
        try {
            $response = $this->makeApiRequest('me');
            return isset($response['id']);
        } catch (Exception $e) {
            return false;
        }
    }
    
    private function createPhotoPost(string $targetId, string $message, array $mediaUrls): array {
        $mediaUrl = $mediaUrls[0]; // Facebook supports one main photo per post
        
        $postParams = [
            'message' => $message,
            'url' => $mediaUrl
        ];
        
        $response = $this->makeApiRequest("{$targetId}/photos", $postParams, 'POST');
        
        return [
            'success' => true,
            'post_id' => $response['id'] ?? null,
            'post_type' => 'photo',
            'platform_response' => $response
        ];
    }
    
    private function createVideoPost(string $targetId, string $description, string $videoUrl): array {
        $postParams = [
            'description' => $description,
            'file_url' => $videoUrl
        ];
        
        $response = $this->makeApiRequest("{$targetId}/videos", $postParams, 'POST');
        
        return [
            'success' => true,
            'post_id' => $response['id'] ?? null,
            'post_type' => 'video',
            'platform_response' => $response
        ];
    }
    
    private function uploadWithCurl(string $endpoint, array $postData): array {
        $url = $this->apiBaseUrl . $endpoint;
        
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $postData,
            CURLOPT_TIMEOUT => 120, // Longer timeout for uploads
            CURLOPT_SSL_VERIFYPEER => false
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);
        
        if ($error) {
            throw new Exception("Facebook upload failed: " . $error);
        }
        
        $decodedResponse = json_decode($response, true);
        
        if ($httpCode >= 400) {
            $errorMsg = $decodedResponse['error']['message'] ?? 'Unknown Facebook upload error';
            throw new Exception("Facebook upload error ({$httpCode}): " . $errorMsg);
        }
        
        return $decodedResponse ?: [];
    }
    
    private function calculateEngagementScore(array $post): float {
        $likes = $post['likes']['summary']['total_count'] ?? 0;
        $comments = $post['comments']['summary']['total_count'] ?? 0;
        $shares = $post['shares']['count'] ?? 0;
        
        // Weighted engagement score
        return ($likes * 1) + ($comments * 3) + ($shares * 5);
    }
    
    protected function makeApiRequest(string $endpoint, array $data = [], string $method = 'GET'): array {
        $url = $this->apiBaseUrl . $endpoint;
        
        // Add access token to request
        if ($method === 'GET') {
            $separator = strpos($url, '?') !== false ? '&' : '?';
            $url .= $separator . 'access_token=' . urlencode($this->accessToken);
        } else {
            $data['access_token'] = $this->accessToken;
        }
        
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_SSL_VERIFYPEER => false
        ]);
        
        if ($method === 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
        } elseif ($method === 'DELETE') {
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'DELETE');
        }
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);
        
        if ($error) {
            throw new Exception("Facebook API request failed: " . $error);
        }
        
        $decodedResponse = json_decode($response, true);
        
        if ($httpCode >= 400) {
            $errorMsg = 'Unknown Facebook API error';
            if (isset($decodedResponse['error']['message'])) {
                $errorMsg = $decodedResponse['error']['message'];
            }
            throw new Exception("Facebook API error ({$httpCode}): " . $errorMsg);
        }
        
        return $decodedResponse ?: [];
    }
}