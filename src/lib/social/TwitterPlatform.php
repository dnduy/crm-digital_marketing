<?php
// ==========================
// FILE: /lib/social/TwitterPlatform.php
// Twitter/X Platform Handler with API v2 integration
// ==========================

require_once __DIR__ . '/SocialMediaPlatformInterface.php';

class TwitterPlatform extends AbstractSocialMediaPlatform {
    private $apiKey;
    private $apiSecret;
    private $bearerToken;
    
    protected function initializePlatform(): void {
        $this->platformName = 'twitter';
        $this->apiBaseUrl = 'https://api.twitter.com/2/';
        $this->rateLimits = [
            'posts_per_hour' => 300,
            'posts_per_day' => 2400
        ];
    }
    
    public function authenticate(array $credentials): bool {
        try {
            $this->apiKey = $credentials['api_key'] ?? '';
            $this->apiSecret = $credentials['api_secret'] ?? '';
            $this->bearerToken = $credentials['bearer_token'] ?? '';
            $this->accessToken = $credentials['access_token'] ?? '';
            
            if (empty($this->bearerToken) && !empty($this->apiKey) && !empty($this->apiSecret)) {
                $this->bearerToken = $this->generateBearerToken();
            }
            
            return $this->validateToken();
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
            if (strlen($content) > 280) {
                throw new Exception('Content exceeds 280 character limit');
            }
            
            $tweetData = ['text' => $content];
            
            // Handle media attachments
            if (!empty($postData['media_urls'])) {
                $mediaIds = [];
                foreach ($postData['media_urls'] as $mediaUrl) {
                    $mediaId = $this->uploadMediaFromUrl($mediaUrl);
                    if ($mediaId) {
                        $mediaIds[] = $mediaId;
                    }
                }
                if (!empty($mediaIds)) {
                    $tweetData['media'] = ['media_ids' => $mediaIds];
                }
            }
            
            // Handle reply or quote tweet
            if (!empty($postData['reply_to_id'])) {
                $tweetData['reply'] = ['in_reply_to_tweet_id' => $postData['reply_to_id']];
            }
            
            $response = $this->makeApiRequest('tweets', $tweetData, 'POST');
            
            $this->logActivity('post_created', [
                'tweet_id' => $response['data']['id'] ?? null,
                'content_length' => strlen($content)
            ]);
            
            return [
                'success' => true,
                'post_id' => $response['data']['id'] ?? null,
                'platform_response' => $response
            ];
            
        } catch (Exception $e) {
            return $this->handleApiError($e);
        }
    }
    
    public function schedulePost(array $postData, DateTime $scheduleTime): array {
        // Twitter API v2 doesn't support native scheduling
        // This would require integration with a third-party service or custom queue
        return [
            'success' => false,
            'error' => 'Twitter API does not support native post scheduling. Use third-party tools or custom queue.',
            'suggested_solution' => 'Implement with database queue and cron job'
        ];
    }
    
    public function getAccountInfo(): array {
        try {
            $response = $this->makeApiRequest('users/me?user.fields=created_at,description,entities,id,location,name,pinned_tweet_id,profile_image_url,protected,public_metrics,url,username,verified');
            
            return [
                'success' => true,
                'account_id' => $response['data']['id'] ?? null,
                'username' => $response['data']['username'] ?? null,
                'display_name' => $response['data']['name'] ?? null,
                'description' => $response['data']['description'] ?? null,
                'followers_count' => $response['data']['public_metrics']['followers_count'] ?? 0,
                'following_count' => $response['data']['public_metrics']['following_count'] ?? 0,
                'tweet_count' => $response['data']['public_metrics']['tweet_count'] ?? 0,
                'listed_count' => $response['data']['public_metrics']['listed_count'] ?? 0,
                'verified' => $response['data']['verified'] ?? false,
                'profile_image_url' => $response['data']['profile_image_url'] ?? null,
                'raw_data' => $response
            ];
            
        } catch (Exception $e) {
            return $this->handleApiError($e);
        }
    }
    
    public function getAnalytics(array $options = []): array {
        try {
            $userId = $options['user_id'] ?? 'me';
            $maxResults = min($options['max_results'] ?? 10, 100);
            
            // Get recent tweets with metrics
            $response = $this->makeApiRequest("users/{$userId}/tweets?tweet.fields=created_at,author_id,conversation_id,in_reply_to_user_id,public_metrics,context_annotations&max_results={$maxResults}");
            
            $analytics = [];
            foreach ($response['data'] ?? [] as $tweet) {
                $metrics = $tweet['public_metrics'] ?? [];
                $analytics[] = [
                    'post_id' => $tweet['id'],
                    'created_at' => $tweet['created_at'],
                    'likes_count' => $metrics['like_count'] ?? 0,
                    'retweets_count' => $metrics['retweet_count'] ?? 0,
                    'replies_count' => $metrics['reply_count'] ?? 0,
                    'quotes_count' => $metrics['quote_count'] ?? 0,
                    'engagement_score' => $this->calculateEngagementScore($metrics)
                ];
            }
            
            return [
                'success' => true,
                'analytics' => $analytics,
                'total_tweets' => count($analytics)
            ];
            
        } catch (Exception $e) {
            return $this->handleApiError($e);
        }
    }
    
    public function getAudienceInsights(): array {
        // Twitter API v2 has limited audience insights in free tier
        try {
            $accountInfo = $this->getAccountInfo();
            
            return [
                'success' => true,
                'total_followers' => $accountInfo['followers_count'] ?? 0,
                'total_following' => $accountInfo['following_count'] ?? 0,
                'engagement_rate' => 'Premium feature - requires Twitter API Pro',
                'audience_demographics' => 'Premium feature - requires Twitter API Pro',
                'note' => 'Advanced audience insights require Twitter API Pro subscription'
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
            
            // Twitter media upload uses v1.1 API
            $uploadUrl = 'https://upload.twitter.com/1.1/media/upload.json';
            
            $mediaData = base64_encode(file_get_contents($filePath));
            $postData = [
                'media_data' => $mediaData
            ];
            
            $headers = [
                'Authorization: Bearer ' . $this->bearerToken,
                'Content-Type: application/x-www-form-urlencoded'
            ];
            
            $ch = curl_init();
            curl_setopt_array($ch, [
                CURLOPT_URL => $uploadUrl,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => http_build_query($postData),
                CURLOPT_HTTPHEADER => $headers,
                CURLOPT_TIMEOUT => 60
            ]);
            
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            
            if ($httpCode !== 200) {
                throw new Exception('Media upload failed');
            }
            
            $responseData = json_decode($response, true);
            
            return [
                'success' => true,
                'media_id' => $responseData['media_id_string'] ?? null,
                'media_url' => $responseData['media_url'] ?? null
            ];
            
        } catch (Exception $e) {
            return $this->handleApiError($e);
        }
    }
    
    public function deletePost(string $postId): bool {
        try {
            $response = $this->makeApiRequest("tweets/{$postId}", [], 'DELETE');
            
            $this->logActivity('post_deleted', ['tweet_id' => $postId]);
            
            return isset($response['data']['deleted']) && $response['data']['deleted'] === true;
            
        } catch (Exception $e) {
            $this->logActivity('delete_failed', ['tweet_id' => $postId, 'error' => $e->getMessage()]);
            return false;
        }
    }
    
    public function getPostingGuidelines(): array {
        return [
            'max_content_length' => 280,
            'max_media_count' => 4,
            'supported_media_types' => ['image/jpeg', 'image/png', 'image/gif', 'video/mp4'],
            'max_media_size' => '5MB for images, 512MB for videos',
            'hashtag_recommendations' => 'Use 1-3 relevant hashtags',
            'posting_frequency' => 'Max 300 tweets per hour, 2400 per day',
            'best_posting_times' => ['9:00-10:00', '12:00-13:00', '17:00-18:00'],
            'character_counting' => 'URLs count as 23 characters, media attachments don\'t count'
        ];
    }
    
    public function supportsContentType(string $type): bool {
        $supportedTypes = ['text', 'image', 'video', 'gif'];
        return in_array($type, $supportedTypes);
    }
    
    public function validateToken(): bool {
        try {
            $response = $this->makeApiRequest('users/me');
            return isset($response['data']['id']);
        } catch (Exception $e) {
            return false;
        }
    }
    
    private function generateBearerToken(): string {
        $credentials = base64_encode($this->apiKey . ':' . $this->apiSecret);
        
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => 'https://api.twitter.com/oauth2/token',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => 'grant_type=client_credentials',
            CURLOPT_HTTPHEADER => [
                'Authorization: Basic ' . $credentials,
                'Content-Type: application/x-www-form-urlencoded'
            ]
        ]);
        
        $response = curl_exec($ch);
        curl_close($ch);
        
        $data = json_decode($response, true);
        return $data['access_token'] ?? '';
    }
    
    private function uploadMediaFromUrl(string $url): ?string {
        $tempFile = tempnam(sys_get_temp_dir(), 'twitter_media_');
        file_put_contents($tempFile, file_get_contents($url));
        
        $result = $this->uploadMedia($tempFile);
        unlink($tempFile);
        
        return $result['success'] ? $result['media_id'] : null;
    }
    
    private function calculateEngagementScore(array $metrics): float {
        $likes = $metrics['like_count'] ?? 0;
        $retweets = $metrics['retweet_count'] ?? 0;
        $replies = $metrics['reply_count'] ?? 0;
        $quotes = $metrics['quote_count'] ?? 0;
        
        // Weighted engagement score
        return ($likes * 1) + ($retweets * 3) + ($replies * 2) + ($quotes * 2);
    }
    
    protected function makeApiRequest(string $endpoint, array $data = [], string $method = 'GET'): array {
        $url = $this->apiBaseUrl . $endpoint;
        
        $headers = [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $this->bearerToken
        ];
        
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_SSL_VERIFYPEER => false
        ]);
        
        if ($method === 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        } elseif ($method === 'DELETE') {
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'DELETE');
        }
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);
        
        if ($error) {
            throw new Exception("Twitter API request failed: " . $error);
        }
        
        $decodedResponse = json_decode($response, true);
        
        if ($httpCode >= 400) {
            $errorMsg = 'Unknown Twitter API error';
            if (isset($decodedResponse['errors'][0]['message'])) {
                $errorMsg = $decodedResponse['errors'][0]['message'];
            } elseif (isset($decodedResponse['error'])) {
                $errorMsg = $decodedResponse['error'];
            }
            throw new Exception("Twitter API error ({$httpCode}): " . $errorMsg);
        }
        
        return $decodedResponse ?: [];
    }
}