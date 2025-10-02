<?php
/**
 * Migration: Create Automation Tables
 * 
 * Creates tables for advanced social media automation including:
 * - Social media automation rules
 * - Content calendar with scheduling
 * - Job queue for background processing
 * - Engagement automation settings
 * - Performance-based optimization data
 */

require_once __DIR__ . '/../lib/db.php';

function createAutomationTables() {
    global $db;
    $pdo = $db;
    
    try {
        $pdo->beginTransaction();
        
        // 1. Automation Rules Table
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS social_media_automation_rules (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                name VARCHAR(255) NOT NULL,
                description TEXT,
                platforms TEXT NOT NULL, -- JSON array of platform names
                account_ids TEXT, -- JSON array of account IDs, null = all accounts
                rule_type VARCHAR(50) NOT NULL CHECK (rule_type IN ('scheduled_post', 'auto_reply', 'content_curation', 'engagement_boost', 'optimal_timing')),
                trigger_conditions TEXT NOT NULL, -- JSON object with conditions
                actions TEXT NOT NULL, -- JSON object with actions to take
                is_active BOOLEAN DEFAULT 1,
                priority INTEGER DEFAULT 0,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                last_executed_at TIMESTAMP NULL,
                execution_count INTEGER DEFAULT 0
            )
        ");
        
        // 2. Content Calendar Table
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS content_calendar_extended (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                calendar_id VARCHAR(100) NOT NULL UNIQUE,
                title VARCHAR(255) NOT NULL,
                description TEXT,
                content_type VARCHAR(50) NOT NULL CHECK (content_type IN ('post', 'story', 'video', 'image', 'article', 'poll', 'live')),
                content_data TEXT NOT NULL, -- JSON object with content details
                platforms TEXT NOT NULL, -- JSON array of platforms
                account_ids TEXT, -- JSON array of account IDs
                scheduled_at TIMESTAMP NOT NULL,
                timezone VARCHAR(50) DEFAULT 'UTC',
                status VARCHAR(30) DEFAULT 'scheduled' CHECK (status IN ('draft', 'scheduled', 'publishing', 'published', 'failed', 'cancelled')),
                auto_optimize BOOLEAN DEFAULT 0, -- Whether to auto-optimize posting time
                recurring_rule TEXT, -- JSON object for recurring posts
                approval_required BOOLEAN DEFAULT 0,
                approved_by INTEGER NULL,
                approved_at TIMESTAMP NULL,
                created_by INTEGER NOT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                published_at TIMESTAMP NULL,
                engagement_prediction REAL DEFAULT 0.0,
                tags TEXT -- JSON array of tags
            )
        ");
        
        // 3. Job Queue Table
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS automation_job_queue (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                job_id VARCHAR(100) NOT NULL UNIQUE,
                job_type VARCHAR(50) NOT NULL CHECK (job_type IN ('post_content', 'auto_reply', 'analytics_sync', 'engagement_check', 'optimization', 'backup')),
                payload TEXT NOT NULL, -- JSON object with job data
                priority INTEGER DEFAULT 0,
                status VARCHAR(30) DEFAULT 'pending' CHECK (status IN ('pending', 'processing', 'completed', 'failed', 'cancelled', 'retrying')),
                scheduled_at TIMESTAMP NOT NULL,
                started_at TIMESTAMP NULL,
                completed_at TIMESTAMP NULL,
                attempts INTEGER DEFAULT 0,
                max_attempts INTEGER DEFAULT 3,
                error_message TEXT NULL,
                result_data TEXT NULL, -- JSON object with job results
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )
        ");
        
        // 4. Engagement Automation Table
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS engagement_automation (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                rule_name VARCHAR(255) NOT NULL,
                platform VARCHAR(50) NOT NULL,
                account_id INTEGER NOT NULL,
                trigger_type VARCHAR(50) NOT NULL CHECK (trigger_type IN ('mention', 'comment', 'dm', 'hashtag', 'keyword', 'follower')),
                trigger_conditions TEXT NOT NULL, -- JSON object with conditions
                response_type VARCHAR(50) NOT NULL CHECK (response_type IN ('auto_reply', 'like', 'follow', 'share', 'dm', 'ignore')),
                response_template TEXT, -- Template for auto-replies
                is_active BOOLEAN DEFAULT 1,
                cooldown_minutes INTEGER DEFAULT 60, -- Prevent spam
                daily_limit INTEGER DEFAULT 50, -- Max actions per day
                actions_today INTEGER DEFAULT 0,
                last_reset_date DATE DEFAULT CURRENT_DATE,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (account_id) REFERENCES social_media_accounts(id) ON DELETE CASCADE
            )
        ");
        
        // 5. Performance Optimization Data
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS posting_optimization_data (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                platform VARCHAR(50) NOT NULL,
                account_id INTEGER NOT NULL,
                content_type VARCHAR(50) NOT NULL,
                day_of_week INTEGER NOT NULL CHECK (day_of_week BETWEEN 0 AND 6), -- 0=Sunday
                hour_of_day INTEGER NOT NULL CHECK (hour_of_day BETWEEN 0 AND 23),
                timezone VARCHAR(50) DEFAULT 'UTC',
                avg_engagement_rate REAL DEFAULT 0.0,
                avg_reach INTEGER DEFAULT 0,
                avg_impressions INTEGER DEFAULT 0,
                post_count INTEGER DEFAULT 0,
                last_calculated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                confidence_score REAL DEFAULT 0.0, -- How confident we are in this data
                FOREIGN KEY (account_id) REFERENCES social_media_accounts(id) ON DELETE CASCADE,
                UNIQUE(platform, account_id, content_type, day_of_week, hour_of_day, timezone)
            )
        ");
        
        // 6. Automation Logs Table
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS automation_logs (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                log_type VARCHAR(50) NOT NULL CHECK (log_type IN ('rule_execution', 'job_processing', 'engagement_action', 'optimization', 'error')),
                rule_id INTEGER NULL,
                job_id VARCHAR(100) NULL,
                account_id INTEGER NULL,
                platform VARCHAR(50) NULL,
                action_taken VARCHAR(100) NOT NULL,
                details TEXT, -- JSON object with additional details
                status VARCHAR(30) NOT NULL CHECK (status IN ('success', 'warning', 'error', 'info')),
                error_message TEXT NULL,
                execution_time_ms INTEGER DEFAULT 0,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (rule_id) REFERENCES social_media_automation_rules(id) ON DELETE SET NULL,
                FOREIGN KEY (account_id) REFERENCES social_media_accounts(id) ON DELETE SET NULL
            )
        ");
        
        // Create indexes for performance
        $indexes = [
            "CREATE INDEX IF NOT EXISTS idx_automation_rules_active ON social_media_automation_rules(is_active, rule_type)",
            "CREATE INDEX IF NOT EXISTS idx_calendar_scheduled ON content_calendar_extended(scheduled_at, status)",
            "CREATE INDEX IF NOT EXISTS idx_job_queue_status ON automation_job_queue(status, scheduled_at, priority)",
            "CREATE INDEX IF NOT EXISTS idx_engagement_active ON engagement_automation(is_active, platform, account_id)",
            "CREATE INDEX IF NOT EXISTS idx_optimization_performance ON posting_optimization_data(platform, account_id, day_of_week, hour_of_day)",
            "CREATE INDEX IF NOT EXISTS idx_automation_logs_time ON automation_logs(created_at, log_type)"
        ];
        
        foreach ($indexes as $index) {
            $pdo->exec($index);
        }
        
        $pdo->commit();
        echo "✅ Successfully created automation tables:\n";
        echo "   - social_media_automation_rules\n";
        echo "   - content_calendar_extended\n";
        echo "   - automation_job_queue\n";
        echo "   - engagement_automation\n";
        echo "   - posting_optimization_data\n";
        echo "   - automation_logs\n";
        echo "   - Created 6 performance indexes\n";
        
    } catch (Exception $e) {
        $pdo->rollBack();
        die("❌ Error creating automation tables: " . $e->getMessage() . "\n");
    }
}

// Run the migration
if (php_sapi_name() === 'cli') {
    echo "🚀 Running Automation Tables Migration...\n";
    createAutomationTables();
}