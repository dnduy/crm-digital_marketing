<?php
/**
 * Competitor Tracking and Analysis System
 * 
 * Monitors competitor activities across social media platforms including:
 * - Competitor profile tracking
 * - Post performance analysis
 * - Engagement pattern analysis
 * - Content strategy insights
 * - Competitive benchmarking
 */

require_once __DIR__ . '/../lib/db.php';
require_once __DIR__ . '/SentimentAnalysisEngine.php';

class CompetitorTrackingSystem {
    private $db;
    private $sentimentEngine;
    
    public function __construct() {
        global $db;
        $this->db = $db;
        $this->sentimentEngine = new SentimentAnalysisEngine();
    }
    
    /**
     * Add a new competitor to track
     */
    public function addCompetitor($competitorName, $platform, $username, $profileUrl = null, $industry = null) {
        try {
            $stmt = $this->db->prepare("
                INSERT INTO competitor_tracking (
                    competitor_name, platform, username, profile_url, industry
                ) VALUES (?, ?, ?, ?, ?)
            ");
            
            $stmt->execute([$competitorName, $platform, $username, $profileUrl, $industry]);
            $competitorId = $this->db->lastInsertId();
            
            // Perform initial analysis
            $this->analyzeCompetitor($competitorId);
            
            return $competitorId;
            
        } catch (PDOException $e) {
            if ($e->getCode() == 23000) { // Unique constraint violation
                throw new Exception("Competitor already exists on this platform");
            }
            throw $e;
        }
    }
    
    /**
     * Analyze competitor profile and update metrics
     */
    public function analyzeCompetitor($competitorId) {
        $competitor = $this->getCompetitor($competitorId);
        if (!$competitor) {
            throw new Exception("Competitor not found");
        }
        
        // Simulate API data collection (in real implementation, this would call platform APIs)
        $profileData = $this->fetchCompetitorProfileData($competitor);
        $recentPosts = $this->fetchCompetitorPosts($competitor);
        
        // Update competitor metrics
        $this->updateCompetitorMetrics($competitorId, $profileData);
        
        // Analyze posting patterns
        $postingInsights = $this->analyzePostingPatterns($recentPosts);
        
        // Update competitive score
        $competitiveScore = $this->calculateCompetitiveScore($competitor, $profileData, $postingInsights);
        
        $stmt = $this->db->prepare("
            UPDATE competitor_tracking 
            SET competitive_score = ?, last_analyzed_at = CURRENT_TIMESTAMP, updated_at = CURRENT_TIMESTAMP
            WHERE id = ?
        ");
        $stmt->execute([$competitiveScore, $competitorId]);
        
        return [
            'competitor' => $competitor,
            'profile_data' => $profileData,
            'posting_insights' => $postingInsights,
            'competitive_score' => $competitiveScore
        ];
    }
    
    /**
     * Simulate fetching competitor profile data
     */
    private function fetchCompetitorProfileData($competitor) {
        // In real implementation, this would use platform APIs
        // For demo purposes, we'll simulate realistic data
        
        $baseFollowers = rand(1000, 100000);
        $variation = rand(-5, 15); // -5% to +15% growth
        
        return [
            'follower_count' => $baseFollowers + intval($baseFollowers * $variation / 100),
            'following_count' => rand(100, 5000),
            'post_count' => rand(50, 2000),
            'engagement_rate' => round(rand(150, 800) / 100, 2), // 1.5% - 8%
            'avg_likes' => rand(50, 1000),
            'avg_comments' => rand(5, 100),
            'avg_shares' => rand(2, 50),
            'posting_frequency' => round(rand(3, 20) / 10, 1), // 0.3 - 2.0 posts per day
        ];
    }
    
    /**
     * Simulate fetching competitor posts
     */
    private function fetchCompetitorPosts($competitor, $limit = 20) {
        // Simulate fetching recent posts
        $posts = [];
        
        for ($i = 0; $i < $limit; $i++) {
            $daysAgo = rand(0, 30);
            $posts[] = [
                'platform_post_id' => 'sim_' . uniqid(),
                'content_text' => $this->generateSampleContent(),
                'content_type' => rand(0, 10) > 7 ? 'video' : 'post',
                'hashtags' => $this->generateSampleHashtags(),
                'likes_count' => rand(10, 1000),
                'comments_count' => rand(1, 100),
                'shares_count' => rand(0, 50),
                'posted_at' => date('Y-m-d H:i:s', strtotime("-$daysAgo days")),
            ];
        }
        
        return $posts;
    }
    
    /**
     * Generate sample content for demo
     */
    private function generateSampleContent() {
        $samples = [
            "Excited to share our latest product innovation! What do you think?",
            "Behind the scenes at our team meeting discussing future plans",
            "Customer success story that made our day! Thank you for the feedback",
            "Industry insights: The future of digital transformation looks bright",
            "Join us at the upcoming conference - see you there!",
            "New blog post is live! Check out our thoughts on market trends",
            "Team spotlight: Meet our amazing marketing director",
            "Quick tip for improving your productivity today"
        ];
        
        return $samples[array_rand($samples)];
    }
    
    /**
     * Generate sample hashtags
     */
    private function generateSampleHashtags() {
        $allHashtags = [
            '#marketing', '#business', '#innovation', '#technology', '#growth',
            '#success', '#teamwork', '#leadership', '#productivity', '#digital'
        ];
        
        $count = rand(2, 5);
        $selected = array_rand($allHashtags, $count);
        
        if (is_array($selected)) {
            return array_map(function($index) use ($allHashtags) {
                return $allHashtags[$index];
            }, $selected);
        } else {
            return [$allHashtags[$selected]];
        }
    }
    
    /**
     * Update competitor metrics in database
     */
    private function updateCompetitorMetrics($competitorId, $profileData) {
        $stmt = $this->db->prepare("
            UPDATE competitor_tracking 
            SET 
                follower_count = ?,
                following_count = ?,
                post_count = ?,
                engagement_rate = ?,
                avg_likes = ?,
                avg_comments = ?,
                avg_shares = ?,
                posting_frequency = ?,
                updated_at = CURRENT_TIMESTAMP
            WHERE id = ?
        ");
        
        $stmt->execute([
            $profileData['follower_count'],
            $profileData['following_count'],
            $profileData['post_count'],
            $profileData['engagement_rate'],
            $profileData['avg_likes'],
            $profileData['avg_comments'],
            $profileData['avg_shares'],
            $profileData['posting_frequency'],
            $competitorId
        ]);
    }
    
    /**
     * Store competitor posts in database
     */
    public function storeCompetitorPosts($competitorId, $posts) {
        $stored = 0;
        
        foreach ($posts as $post) {
            try {
                // Calculate engagement rate
                $totalEngagement = $post['likes_count'] + $post['comments_count'] + $post['shares_count'];
                $engagementRate = $totalEngagement > 0 ? round($totalEngagement / 1000, 2) : 0; // Simplified calculation
                
                // Analyze sentiment
                $sentimentResult = $this->sentimentEngine->analyzeSentiment(
                    $post['content_text'],
                    null,
                    null,
                    'competitor_tracking'
                );
                
                $stmt = $this->db->prepare("
                    INSERT OR IGNORE INTO competitor_posts (
                        competitor_id, platform_post_id, content_text, content_type,
                        hashtags, likes_count, comments_count, shares_count,
                        engagement_rate, sentiment_score, posted_at
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                ");
                
                $stmt->execute([
                    $competitorId,
                    $post['platform_post_id'],
                    $post['content_text'],
                    $post['content_type'],
                    json_encode($post['hashtags']),
                    $post['likes_count'],
                    $post['comments_count'],
                    $post['shares_count'],
                    $engagementRate,
                    $sentimentResult['sentiment_score'],
                    $post['posted_at']
                ]);
                
                if ($this->db->lastInsertId()) {
                    $stored++;
                }
                
            } catch (PDOException $e) {
                // Skip duplicates
                continue;
            }
        }
        
        return $stored;
    }
    
    /**
     * Analyze posting patterns
     */
    private function analyzePostingPatterns($posts) {
        if (empty($posts)) {
            return ['posting_times' => [], 'content_categories' => [], 'hashtag_strategy' => []];
        }
        
        $postingTimes = [];
        $contentTypes = [];
        $hashtagUsage = [];
        
        foreach ($posts as $post) {
            // Analyze posting times
            $hour = intval(date('H', strtotime($post['posted_at'])));
            $postingTimes[] = $hour;
            
            // Content types
            $contentTypes[] = $post['content_type'];
            
            // Hashtag analysis
            if (isset($post['hashtags'])) {
                $hashtags = is_array($post['hashtags']) ? $post['hashtags'] : json_decode($post['hashtags'], true);
                if ($hashtags) {
                    $hashtagUsage = array_merge($hashtagUsage, $hashtags);
                }
            }
        }
        
        // Find optimal posting times (most frequent hours)
        $timeFrequency = array_count_values($postingTimes);
        arsort($timeFrequency);
        $optimalTimes = array_slice(array_keys($timeFrequency), 0, 3);
        
        // Content category analysis
        $contentFrequency = array_count_values($contentTypes);
        
        // Top hashtags
        $hashtagFrequency = array_count_values($hashtagUsage);
        arsort($hashtagFrequency);
        $topHashtags = array_slice(array_keys($hashtagFrequency), 0, 10);
        
        return [
            'posting_times' => $optimalTimes,
            'content_categories' => $contentFrequency,
            'hashtag_strategy' => $topHashtags
        ];
    }
    
    /**
     * Calculate competitive score
     */
    private function calculateCompetitiveScore($competitor, $profileData, $postingInsights) {
        $score = 0;
        
        // Follower count (0-25 points)
        $followerScore = min(25, ($profileData['follower_count'] / 10000) * 25);
        $score += $followerScore;
        
        // Engagement rate (0-30 points)
        $engagementScore = min(30, $profileData['engagement_rate'] * 5);
        $score += $engagementScore;
        
        // Posting frequency (0-20 points)
        $frequencyScore = min(20, $profileData['posting_frequency'] * 10);
        $score += $frequencyScore;
        
        // Content diversity (0-15 points)
        $diversityScore = count($postingInsights['content_categories']) * 5;
        $score += min(15, $diversityScore);
        
        // Hashtag strategy (0-10 points)
        $hashtagScore = min(10, count($postingInsights['hashtag_strategy']) / 2);
        $score += $hashtagScore;
        
        return round($score, 1);
    }
    
    /**
     * Get competitor details
     */
    public function getCompetitor($competitorId) {
        $stmt = $this->db->prepare("
            SELECT * FROM competitor_tracking WHERE id = ?
        ");
        $stmt->execute([$competitorId]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    /**
     * Get all competitors
     */
    public function getAllCompetitors($platform = null, $industry = null) {
        $where = ["is_active = 1"];
        $params = [];
        
        if ($platform) {
            $where[] = "platform = ?";
            $params[] = $platform;
        }
        
        if ($industry) {
            $where[] = "industry = ?";
            $params[] = $industry;
        }
        
        $whereClause = implode(" AND ", $where);
        
        $stmt = $this->db->prepare("
            SELECT 
                *,
                (SELECT COUNT(*) FROM competitor_posts WHERE competitor_id = competitor_tracking.id) as posts_tracked
            FROM competitor_tracking 
            WHERE $whereClause
            ORDER BY competitive_score DESC, last_analyzed_at DESC
        ");
        
        $stmt->execute($params);
        $competitors = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Decode JSON fields
        foreach ($competitors as &$competitor) {
            $competitor['content_categories'] = $competitor['content_categories'] ? json_decode($competitor['content_categories'], true) : [];
            $competitor['hashtag_strategy'] = $competitor['hashtag_strategy'] ? json_decode($competitor['hashtag_strategy'], true) : [];
            $competitor['posting_times'] = $competitor['posting_times'] ? json_decode($competitor['posting_times'], true) : [];
        }
        
        return $competitors;
    }
    
    /**
     * Get competitive analysis dashboard data
     */
    public function getCompetitiveDashboard($platform = null, $days = 30) {
        $where = "WHERE ct.is_active = 1";
        $params = [];
        
        if ($platform) {
            $where .= " AND ct.platform = ?";
            $params[] = $platform;
        }
        
        // Top performers
        $stmt = $this->db->prepare("
            SELECT 
                ct.*,
                COUNT(cp.id) as recent_posts,
                AVG(cp.engagement_rate) as avg_recent_engagement,
                AVG(cp.sentiment_score) as avg_sentiment
            FROM competitor_tracking ct
            LEFT JOIN competitor_posts cp ON ct.id = cp.competitor_id 
                AND cp.posted_at >= DATE('now', '-$days days')
            $where
            GROUP BY ct.id
            ORDER BY ct.competitive_score DESC
            LIMIT 10
        ");
        
        $stmt->execute($params);
        $topCompetitors = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Platform distribution
        $stmt = $this->db->query("
            SELECT platform, COUNT(*) as count, AVG(competitive_score) as avg_score
            FROM competitor_tracking 
            WHERE is_active = 1
            GROUP BY platform
            ORDER BY count DESC
        ");
        $platformDistribution = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Recent activity trends
        $stmt = $this->db->prepare("
            SELECT 
                DATE(cp.posted_at) as date,
                COUNT(*) as total_posts,
                AVG(cp.engagement_rate) as avg_engagement,
                AVG(cp.sentiment_score) as avg_sentiment
            FROM competitor_posts cp
            JOIN competitor_tracking ct ON cp.competitor_id = ct.id
            WHERE cp.posted_at >= DATE('now', '-$days days') 
                AND ct.is_active = 1
            " . ($platform ? "AND ct.platform = ?" : "") . "
            GROUP BY DATE(cp.posted_at)
            ORDER BY date DESC
        ");
        
        if ($platform) {
            $stmt->execute([$platform]);
        } else {
            $stmt->execute();
        }
        $activityTrends = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        return [
            'top_competitors' => $topCompetitors,
            'platform_distribution' => $platformDistribution,
            'activity_trends' => $activityTrends,
            'total_competitors' => count($topCompetitors),
            'analysis_period' => $days
        ];
    }
    
    /**
     * Generate competitive insights
     */
    public function generateCompetitiveInsights($competitorId = null, $platform = null) {
        $insights = [];
        
        if ($competitorId) {
            // Single competitor insights
            $competitor = $this->getCompetitor($competitorId);
            if ($competitor) {
                $insights[] = $this->analyzeCompetitorPerformance($competitor);
            }
        } else {
            // Industry insights
            $insights = $this->generateIndustryInsights($platform);
        }
        
        return $insights;
    }
    
    /**
     * Analyze individual competitor performance
     */
    private function analyzeCompetitorPerformance($competitor) {
        $insights = [];
        
        // Engagement analysis
        if ($competitor['engagement_rate'] > 5.0) {
            $insights[] = [
                'type' => 'opportunity',
                'title' => 'High Engagement Rate',
                'description' => "{$competitor['competitor_name']} has an exceptional engagement rate of {$competitor['engagement_rate']}%",
                'recommendation' => 'Analyze their content strategy and posting times'
            ];
        }
        
        // Posting frequency analysis
        if ($competitor['posting_frequency'] > 1.5) {
            $insights[] = [
                'type' => 'trend',
                'title' => 'High Posting Frequency',
                'description' => "{$competitor['competitor_name']} posts {$competitor['posting_frequency']} times per day",
                'recommendation' => 'Consider increasing your posting frequency'
            ];
        }
        
        return $insights;
    }
    
    /**
     * Generate industry-wide insights
     */
    private function generateIndustryInsights($platform) {
        // This would generate broader competitive insights
        // For now, return placeholder insights
        return [
            [
                'type' => 'trend',
                'title' => 'Industry Posting Trends',
                'description' => 'Competitors are posting more video content',
                'recommendation' => 'Consider incorporating more video into your strategy'
            ]
        ];
    }
}