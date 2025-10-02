<?php
// ==========================
// FILE: /lib/social/SocialMediaManager.php
// Social Media Manager - Orchestrates multi-platform posting with AI integration
// ==========================

require_once __DIR__ . '/SocialMediaPlatformInterface.php';
require_once __DIR__ . '/TwitterPlatform.php';
require_once __DIR__ . '/LinkedInPlatform.php';
require_once __DIR__ . '/FacebookPlatform.php';

class SocialMediaManager {
    private $db;
    private $platforms = [];
    private $aiService;
    private $logger;
    
    public function __construct($database, $aiService = null) {
        $this->db = $database;
        $this->aiService = $aiService;
        $this->logger = new Logger();
        $this->initializePlatforms();
    }
    
    /**
     * Initialize all available platforms
     */
    private function initializePlatforms(): void {
        $this->platforms = [
            'twitter' => new TwitterPlatform(),
            'linkedin' => new LinkedInPlatform(),
            'facebook' => new FacebookPlatform()
        ];
    }
    
    /**
     * Connect a social media account
     */
    public function connectAccount(string $platform, array $credentials, array $accountInfo = []): array {
        try {
            if (!isset($this->platforms[$platform])) {
                throw new Exception("Unsupported platform: {$platform}");
            }
            
            $platformHandler = $this->platforms[$platform];
            
            if (!$platformHandler->authenticate($credentials)) {
                throw new Exception("Authentication failed for {$platform}");
            }
            
            // Get account information from platform
            $accountData = $platformHandler->getAccountInfo();
            
            if (!$accountData['success']) {
                throw new Exception("Failed to retrieve account information");
            }
            
            // Store account in database
            $stmt = $this->db->prepare("
                INSERT OR REPLACE INTO social_media_accounts 
                (platform, account_id, account_name, username, display_name, profile_url, 
                 access_token, followers_count, following_count, posts_count, 
                 verification_status, account_meta, connected_at, last_sync_at) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            
            $stmt->execute([
                $platform,
                $accountData['account_id'],
                $accountData['display_name'] ?? $accountData['username'] ?? 'Unknown',
                $accountData['username'],
                $accountData['display_name'],
                $accountData['profile_url'],
                json_encode($credentials),
                $accountData['followers_count'] ?? 0,
                $accountData['following_count'] ?? 0,
                $accountData['posts_count'] ?? 0,
                $accountData['verified'] ? 'verified' : 'none',
                json_encode($accountData['raw_data'] ?? []),
                date('Y-m-d H:i:s'),
                date('Y-m-d H:i:s')
            ]);
            
            $accountId = $this->db->lastInsertId();
            
            $this->logger->info("Social media account connected", [
                'platform' => $platform,
                'account_id' => $accountData['account_id'],
                'db_id' => $accountId
            ]);
            
            return [
                'success' => true,
                'account_id' => $accountId,
                'platform_account_id' => $accountData['account_id'],
                'account_info' => $accountData
            ];
            
        } catch (Exception $e) {
            $this->logger->error("Failed to connect social media account", [
                'platform' => $platform,
                'error' => $e->getMessage()
            ]);
            
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Create and publish post across multiple platforms
     */
    public function createMultiPlatformPost(array $postData, array $targetPlatforms = []): array {
        $results = [];
        $contentRequest = null;
        
        try {
            // Generate AI-optimized content if requested
            if ($postData['ai_generated'] ?? false) {
                $contentRequest = $this->generateAIContent($postData);
                if ($contentRequest) {
                    $postData['content'] = $contentRequest['content'];
                    $postData['hashtags'] = $contentRequest['hashtags'] ?? [];
                }
            }
            
            // If no specific platforms specified, use all connected accounts
            if (empty($targetPlatforms)) {
                $targetPlatforms = $this->getConnectedPlatforms();
            }
            
            foreach ($targetPlatforms as $platform) {
                $result = $this->createPlatformPost($platform, $postData);
                $results[$platform] = $result;
                
                // Store post in database
                if ($result['success']) {
                    $this->storePlatformPost($platform, $postData, $result, $contentRequest);
                }
            }
            
            return [
                'success' => true,
                'results' => $results,
                'summary' => $this->summarizeResults($results)
            ];
            
        } catch (Exception $e) {
            $this->logger->error("Multi-platform post creation failed", [
                'error' => $e->getMessage(),
                'platforms' => $targetPlatforms
            ]);
            
            return [
                'success' => false,
                'error' => $e->getMessage(),
                'partial_results' => $results
            ];
        }
    }
    
    /**
     * Schedule posts across platforms
     */
    public function scheduleMultiPlatformPost(array $postData, DateTime $scheduleTime, array $targetPlatforms = []): array {
        $results = [];
        
        try {
            // Generate AI content if needed
            if ($postData['ai_generated'] ?? false) {
                $contentRequest = $this->generateAIContent($postData);
                if ($contentRequest) {
                    $postData['content'] = $contentRequest['content'];
                    $postData['hashtags'] = $contentRequest['hashtags'] ?? [];
                }
            }
            
            if (empty($targetPlatforms)) {
                $targetPlatforms = $this->getConnectedPlatforms();
            }
            
            foreach ($targetPlatforms as $platform) {
                $result = $this->schedulePlatformPost($platform, $postData, $scheduleTime);
                $results[$platform] = $result;
                
                // Store scheduled post in database
                if ($result['success']) {
                    $this->storeScheduledPost($platform, $postData, $scheduleTime, $result);
                }
            }
            
            return [
                'success' => true,
                'results' => $results,
                'scheduled_time' => $scheduleTime->format('Y-m-d H:i:s'),
                'summary' => $this->summarizeResults($results)
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
                'partial_results' => $results
            ];
        }
    }
    
    /**
     * Generate AI-optimized content for social media
     */
    private function generateAIContent(array $postData): ?array {
        if (!$this->aiService) {
            return null;
        }
        
        try {
            // Create content request for AI service
            $request = [
                'type' => 'social_media',
                'topic' => $postData['topic'] ?? $postData['content'] ?? '',
                'platforms' => $postData['platforms'] ?? ['general'],
                'tone' => $postData['tone'] ?? 'engaging',
                'target_audience' => $postData['target_audience'] ?? 'general',
                'include_hashtags' => true,
                'include_emoji' => $postData['include_emoji'] ?? true,
                'max_length' => $this->getMaxLengthForPlatforms($postData['platforms'] ?? [])
            ];
            
            $result = $this->aiService->generateSocialMediaContent($request);
            
            if ($result && $result['success']) {
                return [
                    'content' => $result['content'],
                    'hashtags' => $result['hashtags'] ?? [],
                    'ai_provider' => $result['provider'] ?? 'unknown',
                    'ai_prompt' => $result['prompt'] ?? ''
                ];
            }
            
        } catch (Exception $e) {
            $this->logger->error("AI content generation failed", [
                'error' => $e->getMessage(),
                'request' => $request ?? []
            ]);
        }
        
        return null;
    }
    
    /**
     * Create post on specific platform
     */
    private function createPlatformPost(string $platform, array $postData): array {
        try {
            $account = $this->getConnectedAccount($platform);
            if (!$account) {
                throw new Exception("No connected account for {$platform}");
            }
            
            $platformHandler = $this->platforms[$platform];
            $credentials = json_decode($account['access_token'], true);
            
            if (!$platformHandler->authenticate($credentials)) {
                throw new Exception("Authentication failed for {$platform}");
            }
            
            // Optimize content for platform
            $optimizedData = $this->optimizeContentForPlatform($platform, $postData);
            
            return $platformHandler->createPost($optimizedData);
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
                'platform' => $platform
            ];
        }
    }
    
    /**
     * Schedule post on specific platform
     */
    private function schedulePlatformPost(string $platform, array $postData, DateTime $scheduleTime): array {
        try {
            $account = $this->getConnectedAccount($platform);
            if (!$account) {
                throw new Exception("No connected account for {$platform}");
            }
            
            $platformHandler = $this->platforms[$platform];
            $credentials = json_decode($account['access_token'], true);
            
            if (!$platformHandler->authenticate($credentials)) {
                throw new Exception("Authentication failed for {$platform}");
            }
            
            $optimizedData = $this->optimizeContentForPlatform($platform, $postData);
            
            return $platformHandler->schedulePost($optimizedData, $scheduleTime);
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
                'platform' => $platform
            ];
        }
    }
    
    /**
     * Optimize content for specific platform
     */
    private function optimizeContentForPlatform(string $platform, array $postData): array {
        $optimized = $postData;
        
        switch ($platform) {
            case 'twitter':
                // Limit to 280 characters
                if (strlen($optimized['content']) > 280) {
                    $optimized['content'] = substr($optimized['content'], 0, 277) . '...';
                }
                break;
                
            case 'linkedin':
                // Professional tone, longer content allowed
                if (strlen($optimized['content']) > 3000) {
                    $optimized['content'] = substr($optimized['content'], 0, 2997) . '...';
                }
                break;
                
            case 'facebook':
                // Facebook allows very long content, no changes needed
                break;
        }
        
        return $optimized;
    }
    
    /**
     * Get connected account for platform
     */
    private function getConnectedAccount(string $platform): ?array {
        $stmt = $this->db->prepare("
            SELECT * FROM social_media_accounts 
            WHERE platform = ? AND account_status = 'active' 
            ORDER BY connected_at DESC LIMIT 1
        ");
        $stmt->execute([$platform]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }
    
    /**
     * Get all connected platforms
     */
    private function getConnectedPlatforms(): array {
        $stmt = $this->db->query("
            SELECT DISTINCT platform FROM social_media_accounts 
            WHERE account_status = 'active'
        ");
        return array_column($stmt->fetchAll(PDO::FETCH_ASSOC), 'platform');
    }
    
    /**
     * Store post in database
     */
    private function storePlatformPost(string $platform, array $postData, array $result, ?array $contentRequest = null): void {
        $account = $this->getConnectedAccount($platform);
        
        $stmt = $this->db->prepare("
            INSERT INTO social_media_posts 
            (account_id, platform, post_id, content, hashtags, mentions, 
             published_at, post_status, ai_generated, ai_provider, ai_prompt, 
             campaign_id, post_meta) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        
        $stmt->execute([
            $account['id'],
            $platform,
            $result['post_id'] ?? null,
            $postData['content'],
            json_encode($postData['hashtags'] ?? []),
            json_encode($postData['mentions'] ?? []),
            date('Y-m-d H:i:s'),
            'published',
            $contentRequest ? 1 : 0,
            $contentRequest['ai_provider'] ?? null,
            $contentRequest['ai_prompt'] ?? null,
            $postData['campaign_id'] ?? null,
            json_encode($result)
        ]);
    }
    
    /**
     * Store scheduled post in database
     */
    private function storeScheduledPost(string $platform, array $postData, DateTime $scheduleTime, array $result): void {
        $account = $this->getConnectedAccount($platform);
        
        $stmt = $this->db->prepare("
            INSERT INTO social_media_posts 
            (account_id, platform, post_id, content, hashtags, scheduled_at, 
             post_status, ai_generated, campaign_id, post_meta) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        
        $stmt->execute([
            $account['id'],
            $platform,
            $result['post_id'] ?? null,
            $postData['content'],
            json_encode($postData['hashtags'] ?? []),
            $scheduleTime->format('Y-m-d H:i:s'),
            'scheduled',
            $postData['ai_generated'] ? 1 : 0,
            $postData['campaign_id'] ?? null,
            json_encode($result)
        ]);
    }
    
    /**
     * Get maximum content length for platforms
     */
    private function getMaxLengthForPlatforms(array $platforms): int {
        $limits = [
            'twitter' => 280,
            'linkedin' => 3000,
            'facebook' => 63206
        ];
        
        if (empty($platforms)) {
            return 280; // Most restrictive
        }
        
        $minLimit = PHP_INT_MAX;
        foreach ($platforms as $platform) {
            if (isset($limits[$platform])) {
                $minLimit = min($minLimit, $limits[$platform]);
            }
        }
        
        return $minLimit === PHP_INT_MAX ? 280 : $minLimit;
    }
    
    /**
     * Summarize multi-platform posting results
     */
    private function summarizeResults(array $results): array {
        $successful = array_filter($results, fn($r) => $r['success'] ?? false);
        $failed = array_filter($results, fn($r) => !($r['success'] ?? false));
        
        return [
            'total_platforms' => count($results),
            'successful_posts' => count($successful),
            'failed_posts' => count($failed),
            'success_rate' => count($results) > 0 ? (count($successful) / count($results)) * 100 : 0,
            'successful_platforms' => array_keys($successful),
            'failed_platforms' => array_keys($failed)
        ];
    }
    
    /**
     * Get analytics across all platforms
     */
    public function getMultiPlatformAnalytics(array $options = []): array {
        $results = [];
        $platforms = $options['platforms'] ?? $this->getConnectedPlatforms();
        
        foreach ($platforms as $platform) {
            try {
                $account = $this->getConnectedAccount($platform);
                if (!$account) continue;
                
                $platformHandler = $this->platforms[$platform];
                $credentials = json_decode($account['access_token'], true);
                
                if ($platformHandler->authenticate($credentials)) {
                    $analytics = $platformHandler->getAnalytics($options);
                    $results[$platform] = $analytics;
                }
            } catch (Exception $e) {
                $results[$platform] = [
                    'success' => false,
                    'error' => $e->getMessage()
                ];
            }
        }
        
        return [
            'success' => true,
            'platforms' => $results,
            'summary' => $this->aggregateAnalytics($results)
        ];
    }
    
    /**
     * Aggregate analytics across platforms
     */
    private function aggregateAnalytics(array $platformResults): array {
        $totalPosts = 0;
        $totalEngagement = 0;
        $totalReach = 0;
        
        foreach ($platformResults as $platform => $result) {
            if ($result['success'] ?? false) {
                $analytics = $result['analytics'] ?? [];
                $totalPosts += count($analytics);
                $totalEngagement += array_sum(array_column($analytics, 'engagement_score'));
                // Add more aggregation logic as needed
            }
        }
        
        return [
            'total_posts' => $totalPosts,
            'total_engagement' => $totalEngagement,
            'average_engagement' => $totalPosts > 0 ? $totalEngagement / $totalPosts : 0
        ];
    }
}