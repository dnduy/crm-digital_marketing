<?php

use Core\Database\Migration;

/**
 * Create enhanced content pages table with AI features
 */
class CreateContentPagesTable extends Migration
{
    public function up(\PDO $db): void
    {
        $this->createTable($db, 'content_pages', [
            'id' => $this->id(),
            'title' => 'VARCHAR(255) NOT NULL',
            'content' => 'LONGTEXT NOT NULL',
            'meta_title' => 'VARCHAR(255)',
            'meta_description' => 'TEXT',
            'meta_keywords' => 'TEXT',
            'target_keywords' => 'TEXT',
            'content_type' => "VARCHAR(50) DEFAULT 'article'",
            'status' => "VARCHAR(20) DEFAULT 'draft'",
            'seo_score' => 'INTEGER DEFAULT 0',
            'ai_generated' => 'BOOLEAN DEFAULT FALSE',
            'author_id' => 'INTEGER',
            'campaign_id' => 'INTEGER',
            'target_platforms' => 'TEXT',
            'publishing_schedule' => 'TEXT', // JSON stored as TEXT in SQLite
            'created_at' => 'TIMESTAMP DEFAULT CURRENT_TIMESTAMP',
            'updated_at' => 'TIMESTAMP DEFAULT CURRENT_TIMESTAMP'
        ]);

        // Add indexes for better performance
        $this->addIndex($db, 'content_pages', ['content_type']);
        $this->addIndex($db, 'content_pages', ['status']);
        $this->addIndex($db, 'content_pages', ['ai_generated']);
        $this->addIndex($db, 'content_pages', ['author_id']);
        $this->addIndex($db, 'content_pages', ['campaign_id']);
        $this->addIndex($db, 'content_pages', ['seo_score']);
        $this->addIndex($db, 'content_pages', ['created_at']);

        // Insert some sample AI-enhanced content
        $this->insertData($db, 'content_pages', [
            [
                'title' => 'The Future of AI in Digital Marketing',
                'content' => 'Artificial Intelligence is revolutionizing digital marketing by enabling personalized customer experiences, predictive analytics, and automated content generation. This comprehensive guide explores how AI technologies are transforming marketing strategies and delivering measurable ROI for businesses across industries.',
                'meta_title' => 'AI in Digital Marketing: Complete Guide 2025',
                'meta_description' => 'Discover how AI is transforming digital marketing with personalized experiences, predictive analytics, and automated content generation. Learn implementation strategies.',
                'meta_keywords' => 'AI marketing, digital marketing, artificial intelligence, automation, personalization',
                'target_keywords' => 'AI digital marketing, marketing automation, AI tools',
                'content_type' => 'blog',
                'status' => 'published',
                'seo_score' => 85,
                'ai_generated' => true,
                'author_id' => 1,
                'target_platforms' => 'facebook,linkedin,twitter',
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s')
            ],
            [
                'title' => 'Social Media Automation Best Practices',
                'content' => 'Effective social media automation requires the right balance between efficiency and authentic engagement. Learn how to implement automation workflows that save time while maintaining genuine connections with your audience.',
                'meta_title' => 'Social Media Automation: Best Practices & Tools',
                'meta_description' => 'Master social media automation with proven strategies, best practices, and tools that boost efficiency while maintaining authentic audience engagement.',
                'meta_keywords' => 'social media automation, marketing automation, social media tools',
                'target_keywords' => 'social media automation, automated posting, social media management',
                'content_type' => 'guide',
                'status' => 'published',
                'seo_score' => 78,
                'ai_generated' => true,
                'author_id' => 1,
                'target_platforms' => 'instagram,facebook,twitter,linkedin',
                'created_at' => date('Y-m-d H:i:s', strtotime('-1 day')),
                'updated_at' => date('Y-m-d H:i:s', strtotime('-1 day'))
            ],
            [
                'title' => 'Content Strategy for 2025: Trends & Predictions',
                'content' => 'As we move into 2025, content strategy is evolving rapidly with new technologies, changing consumer behaviors, and emerging platforms. This article explores the key trends that will shape content marketing in the coming year.',
                'meta_title' => 'Content Strategy 2025: Trends, Tips & Predictions',
                'meta_description' => 'Explore content strategy trends for 2025 including AI-powered content, interactive media, and personalization strategies that drive engagement.',
                'meta_keywords' => 'content strategy, content marketing trends, 2025 marketing',
                'target_keywords' => 'content strategy 2025, marketing trends, content marketing',
                'content_type' => 'article',
                'status' => 'draft',
                'seo_score' => 72,
                'ai_generated' => false,
                'author_id' => 1,
                'target_platforms' => 'linkedin,facebook',
                'created_at' => date('Y-m-d H:i:s', strtotime('-2 hours')),
                'updated_at' => date('Y-m-d H:i:s', strtotime('-1 hour'))
            ]
        ]);
    }

    public function down(\PDO $db): void
    {
        $this->dropTable($db, 'content_pages');
    }
}