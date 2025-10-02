<?php

namespace Repositories;

use Core\Database\Repository;
use PDO;

/**
 * Content Repository
 * Handles content-related database operations
 */
class ContentRepository extends Repository
{
    protected string $table = 'content_pages';
    
    protected array $fillable = [
        'title',
        'slug',
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

    protected array $casts = [
        'seo_score' => 'int',
        'ai_generated' => 'bool',
        'author_id' => 'int',
        'campaign_id' => 'int',
        'publishing_schedule' => 'json'
    ];

    /**
     * Get content by type
     */
    public function getByType(string $type): array
    {
        return $this->query()
            ->where('content_type', $type)
            ->orderBy('created_at', 'DESC')
            ->get();
    }

    /**
     * Get AI-generated content
     */
    public function getAIGenerated(int $limit = null): array
    {
        $query = $this->query()
            ->where('ai_generated', true)
            ->orderBy('created_at', 'DESC');
            
        if ($limit) {
            $query->limit($limit);
        }
        
        return $query->get();
    }

    /**
     * Search content by keywords
     */
    public function searchByKeywords(string $keywords): array
    {
        $searchTerm = "%{$keywords}%";
        
        return $this->query()
            ->whereLike('title', $searchTerm)
            ->orWhere('content', 'LIKE', $searchTerm)
            ->orWhere('meta_keywords', 'LIKE', $searchTerm)
            ->orWhere('target_keywords', 'LIKE', $searchTerm)
            ->orderBy('created_at', 'DESC')
            ->get();
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
        $stmt = $this->query()
            ->raw("SELECT * FROM social_media_posts WHERE content_id = ? ORDER BY created_at DESC", [$id]);
        $posts = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        $content['social_posts'] = $posts;
        return $content;
    }

    /**
     * Get content performance statistics
     */
    public function getPerformanceStats(int $id): array
    {
        $stmt = $this->query()->raw("
            SELECT 
                COUNT(CASE WHEN status = 'published' THEN 1 END) as published_posts,
                COUNT(CASE WHEN status = 'scheduled' THEN 1 END) as scheduled_posts,
                COUNT(CASE WHEN status = 'failed' THEN 1 END) as failed_posts,
                COUNT(*) as total_posts
            FROM social_media_posts 
            WHERE content_id = ?
        ", [$id]);

        $stats = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        $baseStats = $stats[0] ?? [];

        // Get analytics data
        $stmt2 = $this->query()->raw("
            SELECT platform, analytics_data 
            FROM social_media_posts 
            WHERE content_id = ? AND analytics_data IS NOT NULL
        ", [$id]);
        $analyticsData = $stmt2->fetchAll(\PDO::FETCH_ASSOC);

        $totalEngagement = 0;
        $totalImpressions = 0;
        $platformStats = [];

        foreach ($analyticsData as $post) {
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

        return array_merge($baseStats, [
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
        $stmt = $this->query()->raw("
            SELECT c.*, 
                   COUNT(smp.id) as post_count,
                   c.seo_score as avg_seo_score
            FROM {$this->table} c
            LEFT JOIN social_media_posts smp ON c.id = smp.content_id
            WHERE c.status = 'published'
            GROUP BY c.id
            ORDER BY c.seo_score DESC, post_count DESC
            LIMIT ?
        ", [$limit]);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * Get content by status
     */
    public function getByStatus(string $status): array
    {
        return $this->query()
            ->where('status', $status)
            ->orderBy('updated_at', 'DESC')
            ->get();
    }

    /**
     * Get content by author
     */
    public function getByAuthor(int $authorId): array
    {
        return $this->query()
            ->where('author_id', $authorId)
            ->orderBy('created_at', 'DESC')
            ->get();
    }

    /**
     * Get content by campaign
     */
    public function getByCampaign(int $campaignId): array
    {
        return $this->query()
            ->where('campaign_id', $campaignId)
            ->orderBy('created_at', 'DESC')
            ->get();
    }

    /**
     * Get content scheduled for publishing
     */
    public function getScheduledForPublishing(): array
    {
        return $this->query()
            ->where('status', 'draft')
            ->where('publishing_schedule', 'IS NOT NULL')
            ->get();
    }

    /**
     * Update SEO score
     */
    public function updateSEOScore(int $id, int $score): bool
    {
        return $this->update($id, ['seo_score' => $score]);
    }

    /**
     * Mark as AI generated
     */
    public function markAsAIGenerated(int $id): bool
    {
        return $this->update($id, ['ai_generated' => true]);
    }

    /**
     * Get content analytics summary
     */
    public function getAnalyticsSummary(): array
    {
        $stmt = $this->query()->raw("
            SELECT 
                content_type,
                COUNT(*) as count,
                AVG(seo_score) as avg_seo_score,
                COUNT(CASE WHEN ai_generated = 1 THEN 1 END) as ai_generated_count,
                COUNT(CASE WHEN status = 'published' THEN 1 END) as published_count
            FROM {$this->table}
            GROUP BY content_type
            ORDER BY count DESC
        ");

        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * Get recent activity
     */
    public function getRecentActivity(int $limit = 10): array
    {
        return $this->query()
            ->select(['id', 'title', 'status', 'content_type', 'updated_at', 'ai_generated'])
            ->orderBy('updated_at', 'DESC')
            ->limit($limit)
            ->get();
    }

    /**
     * Get searchable columns for full-text search
     */
    protected function getSearchableColumns(): array
    {
        return ['title', 'content', 'meta_description', 'meta_keywords', 'target_keywords'];
    }
}