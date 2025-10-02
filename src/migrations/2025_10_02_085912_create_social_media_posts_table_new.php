<?php

use Core\Database\Migration;

class CreateSocialMediaPostsTableNew extends Migration
{
    /**
     * Run the migration
     */
    public function up(\PDO $db): void
    {
        // Create social media posts table
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
            'analytics_data' => 'TEXT',
            'created_at' => 'TIMESTAMP DEFAULT CURRENT_TIMESTAMP',
            'updated_at' => 'TIMESTAMP DEFAULT CURRENT_TIMESTAMP'
        ]);

        // Add indexes
        $this->addIndex($db, 'social_media_posts', ['content_id']);
        $this->addIndex($db, 'social_media_posts', ['platform']);
        $this->addIndex($db, 'social_media_posts', ['status']);
        $this->addIndex($db, 'social_media_posts', ['scheduled_at']);
    }

    /**
     * Reverse the migration
     */
    public function down(\PDO $db): void
    {
        // Drop social media posts table
        $this->dropTable($db, 'social_media_posts');
    }
}