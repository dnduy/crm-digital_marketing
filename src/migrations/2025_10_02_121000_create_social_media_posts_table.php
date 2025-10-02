<?php

use Core\Database\Migration;

/**
 * Create social media posts table for automation
 */
class CreateSocialMediaPostsTable extends Migration
{
    public function up(\PDO $db): void
    {
        $this->createTable($db, 'social_media_posts', [
            'id' => $this->id(),
            'content_id' => 'INTEGER',
            'platform' => 'VARCHAR(50) NOT NULL',
            'content' => 'TEXT NOT NULL',
            'hashtags' => 'TEXT',
            'mentions' => 'TEXT',
            'status' => "VARCHAR(20) DEFAULT 'draft'",
            'scheduled_at' => 'TIMESTAMP NULL',
            'published_at' => 'TIMESTAMP NULL',
            'platform_post_id' => 'VARCHAR(255)',
            'platform_url' => 'TEXT',
            'image_url' => 'TEXT',
            'video_url' => 'TEXT',
            'error_message' => 'TEXT',
            'analytics_data' => 'TEXT', // JSON stored as TEXT in SQLite
            'created_at' => 'TIMESTAMP DEFAULT CURRENT_TIMESTAMP',
            'updated_at' => 'TIMESTAMP DEFAULT CURRENT_TIMESTAMP'
        ]);

        // Add indexes for performance
        $this->addIndex($db, 'social_media_posts', ['content_id']);
        $this->addIndex($db, 'social_media_posts', ['platform']);
        $this->addIndex($db, 'social_media_posts', ['status']);
        $this->addIndex($db, 'social_media_posts', ['scheduled_at']);
        $this->addIndex($db, 'social_media_posts', ['published_at']);
        $this->addIndex($db, 'social_media_posts', ['created_at']);

        // Insert sample social media posts
        $this->insertData($db, 'social_media_posts', [
            [
                'content_id' => 1,
                'platform' => 'facebook',
                'content' => '🚀 The Future of AI in Digital Marketing is here! Discover how artificial intelligence is transforming customer experiences and boosting ROI. What\'s your experience with AI marketing tools? #AIMarketing #DigitalTransformation #MarketingAutomation',
                'hashtags' => 'AIMarketing,DigitalTransformation,MarketingAutomation,ArtificialIntelligence',
                'mentions' => '',
                'status' => 'published',
                'scheduled_at' => date('Y-m-d H:i:s', strtotime('-2 hours')),
                'published_at' => date('Y-m-d H:i:s', strtotime('-2 hours')),
                'platform_post_id' => 'fb_12345678901234567',
                'platform_url' => 'https://facebook.com/post/12345678901234567',
                'analytics_data' => json_encode([
                    'impressions' => 2500,
                    'engagement' => 85,
                    'clicks' => 42,
                    'reactions' => 38,
                    'shares' => 12,
                    'comments' => 8
                ]),
                'created_at' => date('Y-m-d H:i:s', strtotime('-3 hours')),
                'updated_at' => date('Y-m-d H:i:s', strtotime('-1 hour'))
            ],
            [
                'content_id' => 1,
                'platform' => 'linkedin',
                'content' => 'Artificial Intelligence is revolutionizing digital marketing strategies. From personalized customer journeys to predictive analytics, AI is delivering unprecedented ROI for forward-thinking businesses.

Key benefits we\'re seeing:
✅ 40% improvement in lead quality
✅ 60% reduction in content creation time  
✅ 25% increase in conversion rates

What AI marketing tools are transforming your business? Share your insights below 👇

#AI #DigitalMarketing #MarketingStrategy #BusinessGrowth',
                'hashtags' => 'AI,DigitalMarketing,MarketingStrategy,BusinessGrowth,ArtificialIntelligence',
                'mentions' => '',
                'status' => 'published',
                'scheduled_at' => date('Y-m-d H:i:s', strtotime('-1 hour')),
                'published_at' => date('Y-m-d H:i:s', strtotime('-1 hour')),
                'platform_post_id' => 'ln_activity_1234567890',
                'platform_url' => 'https://linkedin.com/posts/activity-1234567890',
                'analytics_data' => json_encode([
                    'impressions' => 1800,
                    'engagement' => 120,
                    'clicks' => 68,
                    'reactions' => 45,
                    'shares' => 18,
                    'comments' => 15
                ]),
                'created_at' => date('Y-m-d H:i:s', strtotime('-2 hours')),
                'updated_at' => date('Y-m-d H:i:s', strtotime('-30 minutes'))
            ],
            [
                'content_id' => 2,
                'platform' => 'twitter',
                'content' => '🤖 Social media automation doesn\'t mean losing the human touch! 

The key is finding the perfect balance:
⚡ Automate scheduling & posting
💬 Keep conversations authentic
📊 Use data to optimize timing
🎯 Personalize at scale

What\'s your automation strategy? 

#SocialMediaAutomation #MarketingTips #DigitalStrategy',
                'hashtags' => 'SocialMediaAutomation,MarketingTips,DigitalStrategy,Automation',
                'mentions' => '',
                'status' => 'scheduled',
                'scheduled_at' => date('Y-m-d H:i:s', strtotime('+2 hours')),
                'published_at' => null,
                'platform_post_id' => null,
                'platform_url' => null,
                'analytics_data' => null,
                'created_at' => date('Y-m-d H:i:s', strtotime('-30 minutes')),
                'updated_at' => date('Y-m-d H:i:s', strtotime('-30 minutes'))
            ],
            [
                'content_id' => 2,
                'platform' => 'instagram',
                'content' => '✨ Mastering social media automation in 2025! 

Swipe to see our top 5 automation best practices that save 15+ hours per week while boosting engagement by 40%! 

💡 Pro tip: Always leave room for real-time interactions and trending content.

What\'s your biggest automation challenge? Drop a comment below! 👇

#SocialMediaAutomation #MarketingAutomation #DigitalMarketing #ContentStrategy #SocialMediaTips #MarketingHacks #Productivity #BusinessGrowth #SocialMediaManager #ContentCreator',
                'hashtags' => 'SocialMediaAutomation,MarketingAutomation,DigitalMarketing,ContentStrategy,SocialMediaTips,MarketingHacks,Productivity,BusinessGrowth,SocialMediaManager,ContentCreator',
                'mentions' => '',
                'status' => 'draft',
                'scheduled_at' => null,
                'published_at' => null,
                'platform_post_id' => null,
                'platform_url' => null,
                'image_url' => 'https://example.com/images/automation-carousel.jpg',
                'analytics_data' => null,
                'created_at' => date('Y-m-d H:i:s', strtotime('-15 minutes')),
                'updated_at' => date('Y-m-d H:i:s', strtotime('-15 minutes'))
            ]
        ]);
    }

    public function down(\PDO $db): void
    {
        $this->dropTable($db, 'social_media_posts');
    }
}