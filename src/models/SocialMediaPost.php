<?php

namespace Models;

use Core\Model;

/**
 * Social Media Post Model
 */
class SocialMediaPost extends Model
{
    protected string $table = 'social_media_posts';
    
    protected array $fillable = [
        'content_id',
        'platform',
        'content',
        'hashtags',
        'mentions',
        'status',
        'scheduled_at',
        'published_at',
        'platform_post_id',
        'platform_url',
        'image_url',
        'video_url',
        'error_message',
        'analytics_data'
    ];

    /**
     * Get posts by content ID
     */
    public function getByContentId(int $contentId): array
    {
        $sql = "SELECT * FROM {$this->table} WHERE content_id = ? ORDER BY created_at DESC";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$contentId]);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * Get posts by platform
     */
    public function getByPlatform(string $platform): array
    {
        $sql = "SELECT * FROM {$this->table} WHERE platform = ? ORDER BY created_at DESC";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$platform]);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * Get scheduled posts
     */
    public function getScheduledPosts(): array
    {
        $sql = "SELECT * FROM {$this->table} 
                WHERE status = 'scheduled' 
                AND scheduled_at <= NOW() 
                ORDER BY scheduled_at ASC";
        $stmt = $this->db->prepare($sql);
        $stmt->execute();
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * Get posts by status
     */
    public function getByStatus(string $status): array
    {
        $sql = "SELECT * FROM {$this->table} WHERE status = ? ORDER BY created_at DESC";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$status]);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * Update analytics data
     */
    public function updateAnalytics(int $id, array $analytics): bool
    {
        $sql = "UPDATE {$this->table} SET analytics_data = ? WHERE id = ?";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute([json_encode($analytics), $id]);
    }

    /**
     * Initialize table
     */
    public function createTable(): void
    {
        $sql = "CREATE TABLE IF NOT EXISTS {$this->table} (
            id INT AUTO_INCREMENT PRIMARY KEY,
            content_id INT,
            platform VARCHAR(50) NOT NULL,
            content TEXT NOT NULL,
            hashtags TEXT,
            mentions TEXT,
            status ENUM('draft', 'scheduled', 'published', 'failed') DEFAULT 'draft',
            scheduled_at TIMESTAMP NULL,
            published_at TIMESTAMP NULL,
            platform_post_id VARCHAR(255),
            platform_url TEXT,
            image_url TEXT,
            video_url TEXT,
            error_message TEXT,
            analytics_data JSON,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_content_id (content_id),
            INDEX idx_platform (platform),
            INDEX idx_status (status),
            INDEX idx_scheduled (scheduled_at)
        )";
        
        $this->db->exec($sql);
    }
}