<?php
// ==========================
// FILE: /lib/social/SocialMediaPlatformInterface.php
// Social Media Platform Interface - Common contract for all platforms
// ==========================

interface SocialMediaPlatformInterface {
    /**
     * Authenticate and connect to the platform
     */
    public function authenticate(array $credentials): bool;
    
    /**
     * Post content to the platform
     */
    public function createPost(array $postData): array;
    
    /**
     * Schedule a post for later publishing
     */
    public function schedulePost(array $postData, DateTime $scheduleTime): array;
    
    /**
     * Get account information
     */
    public function getAccountInfo(): array;
    
    /**
     * Get analytics/metrics for posts or account
     */
    public function getAnalytics(array $options = []): array;
    
    /**
     * Get follower/audience insights
     */
    public function getAudienceInsights(): array;
    
    /**
     * Upload media (images, videos)
     */
    public function uploadMedia(string $filePath, string $type = 'image'): array;
    
    /**
     * Delete a post
     */
    public function deletePost(string $postId): bool;
    
    /**
     * Get platform-specific posting guidelines
     */
    public function getPostingGuidelines(): array;
    
    /**
     * Check if platform supports specific content type
     */
    public function supportsContentType(string $type): bool;
    
    /**
     * Get platform name
     */
    public function getPlatformName(): string;
    
    /**
     * Validate access token
     */
    public function validateToken(): bool;
}

/**
 * Abstract base class implementing common functionality
 */
abstract class AbstractSocialMediaPlatform implements SocialMediaPlatformInterface {
    protected $accessToken;
    protected $platformName;
    protected $apiBaseUrl;
    protected $rateLimits;
    protected $logger;
    
    public function __construct($accessToken = null) {
        $this->accessToken = $accessToken;
        $this->logger = new Logger();
        $this->initializePlatform();
    }
    
    abstract protected function initializePlatform(): void;
    
    public function getPlatformName(): string {
        return $this->platformName;
    }
    
    /**
     * Make authenticated API request
     */
    protected function makeApiRequest(string $endpoint, array $data = [], string $method = 'GET'): array {
        $url = $this->apiBaseUrl . $endpoint;
        
        $headers = [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $this->accessToken
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
        } elseif ($method === 'PUT') {
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PUT');
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        } elseif ($method === 'DELETE') {
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'DELETE');
        }
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);
        
        if ($error) {
            throw new Exception("API request failed: " . $error);
        }
        
        $decodedResponse = json_decode($response, true);
        
        if ($httpCode >= 400) {
            $errorMsg = $decodedResponse['error']['message'] ?? 'Unknown API error';
            throw new Exception("API error ({$httpCode}): " . $errorMsg);
        }
        
        return $decodedResponse ?: [];
    }
    
    /**
     * Rate limiting check
     */
    protected function checkRateLimit(): bool {
        // Implement rate limiting logic based on platform
        return true;
    }
    
    /**
     * Sanitize content for platform
     */
    protected function sanitizeContent(string $content): string {
        // Remove unsafe characters, check length limits
        return trim($content);
    }
    
    /**
     * Generate hashtags for content
     */
    protected function generateHashtags(string $content, int $maxCount = 10): array {
        // Basic hashtag extraction and generation
        preg_match_all('/#\w+/', $content, $matches);
        return array_slice($matches[0], 0, $maxCount);
    }
    
    /**
     * Log platform activity
     */
    protected function logActivity(string $action, array $data = []): void {
        $this->logger->info("Social Media Platform Activity", [
            'platform' => $this->platformName,
            'action' => $action,
            'data' => $data,
            'timestamp' => date('Y-m-d H:i:s')
        ]);
    }
    
    /**
     * Handle API errors gracefully
     */
    protected function handleApiError(Exception $e): array {
        $this->logActivity('api_error', [
            'error' => $e->getMessage(),
            'code' => $e->getCode()
        ]);
        
        return [
            'success' => false,
            'error' => $e->getMessage(),
            'code' => $e->getCode()
        ];
    }
}

/**
 * Simple logger class
 */
class Logger {
    public function info(string $message, array $context = []): void {
        error_log(sprintf('[INFO] %s: %s %s', 
            date('Y-m-d H:i:s'), 
            $message, 
            json_encode($context)
        ));
    }
    
    public function error(string $message, array $context = []): void {
        error_log(sprintf('[ERROR] %s: %s %s', 
            date('Y-m-d H:i:s'), 
            $message, 
            json_encode($context)
        ));
    }
}