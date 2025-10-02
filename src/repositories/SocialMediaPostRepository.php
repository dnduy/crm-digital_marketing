<?php

namespace Repositories;

use Core\Database\Repository;

/**
 * Social Media Post Repository
 * Handles social media posts database operations
 */
class SocialMediaPostRepository extends Repository
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

    protected array $casts = [
        'content_id' => 'int',
        'analytics_data' => 'json'
    ];

    /**
     * Get posts by content ID
     */
    public function getByContentId(int $contentId): array
    {
        return $this->query()
            ->where('content_id', $contentId)
            ->orderBy('created_at', 'DESC')
            ->get();
    }

    /**
     * Get posts by platform
     */
    public function getByPlatform(string $platform): array
    {
        return $this->query()
            ->where('platform', $platform)
            ->orderBy('created_at', 'DESC')
            ->get();
    }

    /**
     * Get scheduled posts ready for publishing
     */
    public function getScheduledPosts(): array
    {
        return $this->query()
            ->where('status', 'scheduled')
            ->where('scheduled_at', '<=', date('Y-m-d H:i:s'))
            ->orderBy('scheduled_at', 'ASC')
            ->get();
    }

    /**
     * Get posts by status
     */
    public function getByStatus(string $status): array
    {
        return $this->query()
            ->where('status', $status)
            ->orderBy('created_at', 'DESC')
            ->get();
    }

    /**
     * Update analytics data
     */
    public function updateAnalytics(int $id, array $analytics): bool
    {
        return $this->update($id, ['analytics_data' => json_encode($analytics)]);
    }

    /**
     * Mark post as published
     */
    public function markAsPublished(int $id, string $platformPostId = null, string $platformUrl = null): bool
    {
        $data = [
            'status' => 'published',
            'published_at' => date('Y-m-d H:i:s')
        ];

        if ($platformPostId) {
            $data['platform_post_id'] = $platformPostId;
        }

        if ($platformUrl) {
            $data['platform_url'] = $platformUrl;
        }

        return $this->update($id, $data);
    }

    /**
     * Mark post as failed
     */
    public function markAsFailed(int $id, string $errorMessage): bool
    {
        return $this->update($id, [
            'status' => 'failed',
            'error_message' => $errorMessage
        ]);
    }

    /**
     * Get platform statistics
     */
    public function getPlatformStats(): array
    {
        $stmt = $this->query()->raw("
            SELECT 
                platform,
                COUNT(*) as total_posts,
                COUNT(CASE WHEN status = 'published' THEN 1 END) as published_posts,
                COUNT(CASE WHEN status = 'scheduled' THEN 1 END) as scheduled_posts,
                COUNT(CASE WHEN status = 'failed' THEN 1 END) as failed_posts
            FROM {$this->table}
            GROUP BY platform
            ORDER BY total_posts DESC
        ");
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * Get performance analytics
     */
    public function getPerformanceAnalytics(string $platform = null, int $days = 30): array
    {
        $whereClause = "WHERE published_at >= date('now', '-{$days} days') AND analytics_data IS NOT NULL";
        
        if ($platform) {
            $whereClause .= " AND platform = '{$platform}'";
        }

        $stmt = $this->query()->raw("
            SELECT 
                platform,
                COUNT(*) as posts_count,
                AVG(json_extract(analytics_data, '$.impressions')) as avg_impressions,
                AVG(json_extract(analytics_data, '$.engagement')) as avg_engagement,
                SUM(json_extract(analytics_data, '$.impressions')) as total_impressions,
                SUM(json_extract(analytics_data, '$.engagement')) as total_engagement
            FROM {$this->table}
            {$whereClause}
            GROUP BY platform
            ORDER BY total_engagement DESC
        ");
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * Get top performing posts
     */
    public function getTopPerformingPosts(int $limit = 10, string $platform = null): array
    {
        $whereClause = "WHERE status = 'published' AND analytics_data IS NOT NULL";
        if ($platform) {
            $whereClause .= " AND platform = '{$platform}'";
        }

        $stmt = $this->query()->raw("
            SELECT *,
                   json_extract(analytics_data, '$.engagement') as engagement_score
            FROM {$this->table}
            {$whereClause}
            ORDER BY engagement_score DESC
            LIMIT {$limit}
        ");
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * Get posts needing analytics update
     */
    public function getPostsNeedingAnalytics(): array
    {
        return $this->query()
            ->where('status', 'published')
            ->where('analytics_data', 'IS NULL')
            ->where('published_at', '>', date('Y-m-d H:i:s', strtotime('-7 days')))
            ->get();
    }

    /**
     * Get engagement trends
     */
    public function getEngagementTrends(int $days = 30): array
    {
        $stmt = $this->query()->raw("
            SELECT 
                DATE(published_at) as date,
                platform,
                COUNT(*) as posts_count,
                AVG(json_extract(analytics_data, '$.engagement')) as avg_engagement,
                SUM(json_extract(analytics_data, '$.impressions')) as total_impressions
            FROM {$this->table}
            WHERE published_at >= date('now', '-{$days} days')
            AND analytics_data IS NOT NULL
            GROUP BY DATE(published_at), platform
            ORDER BY date DESC, platform
        ");
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * Clean up old posts
     */
    public function cleanupOldPosts(int $daysOld = 90): int
    {
        return $this->query()
            ->where('created_at', '<', date('Y-m-d H:i:s', strtotime("-{$daysOld} days")))
            ->where('status', 'failed')
            ->delete();
    }

    /**
     * Get searchable columns
     */
    protected function getSearchableColumns(): array
    {
        return ['content', 'hashtags', 'mentions'];
    }
}