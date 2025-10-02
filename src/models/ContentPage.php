<?php

namespace Models;

use Core\Model;

/**
 * Content Page Model - Enhanced for AI integration
 */
class ContentPage extends Model
{
    protected string $table = 'content_pages';
    
    protected array $fillable = [
        'title',
        'content',
        'meta_title',
        'meta_description',
        'meta_keywords',
        'target_keywords',
        'content_type',
        'status',
        'seo_score',
        'ai_generated',
        'author_id',
        'campaign_id',
        'target_platforms',
        'publishing_schedule'
    ];

    /**
     * Get content by type
     */
    public function getByType(string $type): array
    {
        $sql = "SELECT * FROM {$this->table} WHERE content_type = ? ORDER BY created_at DESC";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$type]);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * Get AI-generated content
     */
    public function getAIGenerated(): array
    {
        $sql = "SELECT * FROM {$this->table} WHERE ai_generated = 1 ORDER BY created_at DESC";
        $stmt = $this->db->prepare($sql);
        $stmt->execute();
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * Search content by keywords
     */
    public function searchByKeywords(string $keywords): array
    {
        $sql = "SELECT * FROM {$this->table} 
                WHERE MATCH(title, content, meta_keywords, target_keywords) AGAINST(? IN NATURAL LANGUAGE MODE)
                OR title LIKE ? OR content LIKE ?
                ORDER BY created_at DESC";
        
        $searchTerm = "%{$keywords}%";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$keywords, $searchTerm, $searchTerm]);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * Get content with social media posts
     */
    public function getWithSocialPosts(int $id): ?array
    {
        $content = $this->find($id);
        if (!$content) {
            return null;
        }

        // Get associated social media posts
        $sql = "SELECT * FROM social_media_posts WHERE content_id = ? ORDER BY created_at DESC";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$id]);
        $content['social_posts'] = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        return $content;
    }

    /**
     * Get content performance stats
     */
    public function getPerformanceStats(int $id): array
    {
        $sql = "SELECT 
                    COUNT(CASE WHEN status = 'published' THEN 1 END) as published_posts,
                    COUNT(CASE WHEN status = 'scheduled' THEN 1 END) as scheduled_posts,
                    COUNT(CASE WHEN status = 'failed' THEN 1 END) as failed_posts,
                    COUNT(*) as total_posts
                FROM social_media_posts 
                WHERE content_id = ?";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$id]);
        $stats = $stmt->fetch(\PDO::FETCH_ASSOC);

        // Get analytics data
        $sql = "SELECT platform, analytics_data 
                FROM social_media_posts 
                WHERE content_id = ? AND analytics_data IS NOT NULL";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$id]);
        $analytics = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        $totalEngagement = 0;
        $totalImpressions = 0;
        $platformStats = [];

        foreach ($analytics as $post) {
            $data = json_decode($post['analytics_data'], true);
            if ($data) {
                $platform = $post['platform'];
                if (!isset($platformStats[$platform])) {
                    $platformStats[$platform] = [
                        'engagement' => 0,
                        'impressions' => 0,
                        'posts' => 0
                    ];
                }

                $platformStats[$platform]['engagement'] += $data['engagement'] ?? 0;
                $platformStats[$platform]['impressions'] += $data['impressions'] ?? 0;
                $platformStats[$platform]['posts']++;

                $totalEngagement += $data['engagement'] ?? 0;
                $totalImpressions += $data['impressions'] ?? 0;
            }
        }

        return array_merge($stats, [
            'total_engagement' => $totalEngagement,
            'total_impressions' => $totalImpressions,
            'platform_stats' => $platformStats
        ]);
    }

    /**
     * Get top performing content
     */
    public function getTopPerforming(int $limit = 10): array
    {
        $sql = "SELECT c.*, 
                       COUNT(smp.id) as post_count,
                       AVG(c.seo_score) as avg_seo_score
                FROM {$this->table} c
                LEFT JOIN social_media_posts smp ON c.id = smp.content_id
                WHERE c.status = 'published'
                GROUP BY c.id
                ORDER BY avg_seo_score DESC, post_count DESC
                LIMIT ?";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$limit]);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * Initialize enhanced table structure
     */
    public function createTable(): void
    {
        $sql = "CREATE TABLE IF NOT EXISTS {$this->table} (
            id INT AUTO_INCREMENT PRIMARY KEY,
            title VARCHAR(255) NOT NULL,
            content LONGTEXT NOT NULL,
            meta_title VARCHAR(255),
            meta_description TEXT,
            meta_keywords TEXT,
            target_keywords TEXT,
            content_type VARCHAR(50) DEFAULT 'article',
            status ENUM('draft', 'published', 'archived') DEFAULT 'draft',
            seo_score INT DEFAULT 0,
            ai_generated BOOLEAN DEFAULT FALSE,
            author_id INT,
            campaign_id INT,
            target_platforms TEXT,
            publishing_schedule JSON,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FULLTEXT(title, content, meta_keywords, target_keywords),
            INDEX idx_type (content_type),
            INDEX idx_status (status),
            INDEX idx_ai_generated (ai_generated),
            INDEX idx_author (author_id),
            INDEX idx_campaign (campaign_id),
            INDEX idx_seo_score (seo_score)
        )";
        
        $this->db->exec($sql);
    }
}