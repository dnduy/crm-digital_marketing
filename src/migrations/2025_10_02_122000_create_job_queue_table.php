<?php

use Core\Database\Migration;

/**
 * Create job queue table for background processing
 */
class CreateJobQueueTable extends Migration
{
    public function up(\PDO $db): void
    {
        $this->createTable($db, 'job_queue', [
            'id' => $this->id(),
            'job_class' => 'VARCHAR(255) NOT NULL',
            'payload' => 'TEXT',
            'priority' => 'INTEGER DEFAULT 0',
            'delay_seconds' => 'INTEGER DEFAULT 0',
            'max_attempts' => 'INTEGER DEFAULT 3',
            'attempts' => 'INTEGER DEFAULT 0',
            'queue_name' => "VARCHAR(100) DEFAULT 'default'",
            'status' => "VARCHAR(20) DEFAULT 'pending'",
            'created_at' => 'TIMESTAMP DEFAULT CURRENT_TIMESTAMP',
            'available_at' => 'TIMESTAMP DEFAULT CURRENT_TIMESTAMP',
            'started_at' => 'TIMESTAMP NULL',
            'completed_at' => 'TIMESTAMP NULL',
            'failed_at' => 'TIMESTAMP NULL',
            'last_error' => 'TEXT',
            'result' => 'TEXT'
        ]);

        // Add indexes for queue processing performance
        $this->addIndex($db, 'job_queue', ['queue_name', 'status']);
        $this->addIndex($db, 'job_queue', ['available_at']);
        $this->addIndex($db, 'job_queue', ['priority']);
        $this->addIndex($db, 'job_queue', ['status']);
        $this->addIndex($db, 'job_queue', ['created_at']);

        // Create job schedules table for recurring jobs
        $this->createTable($db, 'job_schedules', [
            'id' => $this->id(),
            'job_class' => 'VARCHAR(255) NOT NULL UNIQUE',
            'schedule' => 'VARCHAR(100) NOT NULL',
            'payload' => 'TEXT',
            'last_run' => 'TIMESTAMP NULL',
            'created_at' => 'TIMESTAMP DEFAULT CURRENT_TIMESTAMP',
            'updated_at' => 'TIMESTAMP DEFAULT CURRENT_TIMESTAMP'
        ]);

        // Insert sample scheduled jobs
        $this->insertData($db, 'job_schedules', [
            [
                'job_class' => 'Jobs\\SocialMediaAnalyticsJob',
                'schedule' => '0 */4 * * *', // Every 4 hours
                'payload' => json_encode(['platforms' => ['facebook', 'instagram', 'twitter', 'linkedin']]),
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s')
            ],
            [
                'job_class' => 'Jobs\\ContentSEOOptimizationJob',
                'schedule' => '0 2 * * *', // Daily at 2 AM
                'payload' => json_encode(['batch_size' => 50]),
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s')
            ],
            [
                'job_class' => 'Jobs\\AutoContentGenerationJob',
                'schedule' => '0 10 * * 1,3,5', // Monday, Wednesday, Friday at 10 AM
                'payload' => json_encode([
                    'topics' => ['digital marketing trends', 'AI in business', 'social media tips'],
                    'word_count' => 800,
                    'auto_publish' => false
                ]),
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s')
            ]
        ]);

        // Insert sample queued jobs
        $this->insertData($db, 'job_queue', [
            [
                'job_class' => 'Jobs\\AutoContentGenerationJob',
                'payload' => json_encode([
                    'topic' => 'The Impact of AI on Customer Experience',
                    'type' => 'blog',
                    'keywords' => ['AI customer experience', 'artificial intelligence', 'customer service automation'],
                    'word_count' => 1000,
                    'tone' => 'professional',
                    'language' => 'vi',
                    'social_platforms' => ['facebook', 'linkedin'],
                    'auto_publish' => false
                ]),
                'priority' => 5,
                'queue_name' => 'content-generation',
                'status' => 'pending',
                'created_at' => date('Y-m-d H:i:s'),
                'available_at' => date('Y-m-d H:i:s')
            ],
            [
                'job_class' => 'Jobs\\SocialMediaPublishJob',
                'payload' => json_encode([
                    'post_id' => 3
                ]),
                'priority' => 3,
                'queue_name' => 'social-media',
                'status' => 'pending',
                'created_at' => date('Y-m-d H:i:s', strtotime('-10 minutes')),
                'available_at' => date('Y-m-d H:i:s', strtotime('+2 hours'))
            ],
            [
                'job_class' => 'Jobs\\SocialMediaAnalyticsJob',
                'payload' => json_encode([
                    'post_ids' => [1, 2],
                    'platforms' => ['facebook', 'linkedin']
                ]),
                'priority' => 1,
                'queue_name' => 'analytics',
                'status' => 'completed',
                'created_at' => date('Y-m-d H:i:s', strtotime('-2 hours')),
                'available_at' => date('Y-m-d H:i:s', strtotime('-2 hours')),
                'started_at' => date('Y-m-d H:i:s', strtotime('-2 hours')),
                'completed_at' => date('Y-m-d H:i:s', strtotime('-1 hour 55 minutes')),
                'attempts' => 1,
                'result' => json_encode([
                    'success' => true,
                    'posts_updated' => 2,
                    'analytics_collected' => true
                ])
            ]
        ]);
    }

    public function down(\PDO $db): void
    {
        $this->dropTable($db, 'job_schedules');
        $this->dropTable($db, 'job_queue');
    }
}