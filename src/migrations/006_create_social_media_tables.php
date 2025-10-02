<?php
// ==========================
// FILE: /migrations/006_create_social_media_tables.php
// Social Media Platform Integration - Database Schema
// ==========================

require_once __DIR__ . '/../lib/db.php';

function migration_006_up($db) {
    echo "Creating social media management tables...\n";
    
    // Social Media Accounts - Store connected platform accounts
    $db->exec("
        CREATE TABLE IF NOT EXISTS social_media_accounts (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            platform TEXT NOT NULL CHECK (platform IN ('facebook', 'instagram', 'twitter', 'linkedin', 'tiktok', 'youtube')),
            account_name TEXT NOT NULL,
            account_id TEXT NOT NULL,
            username TEXT,
            display_name TEXT,
            profile_url TEXT,
            access_token TEXT,
            refresh_token TEXT,
            token_expires_at DATETIME,
            account_status TEXT DEFAULT 'active' CHECK (account_status IN ('active', 'expired', 'suspended', 'disconnected')),
            followers_count INTEGER DEFAULT 0,
            following_count INTEGER DEFAULT 0,
            posts_count INTEGER DEFAULT 0,
            verification_status TEXT DEFAULT 'none' CHECK (verification_status IN ('none', 'verified', 'business')),
            account_meta TEXT,
            connected_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            last_sync_at DATETIME,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )
    ");
    
    // Social Media Posts - Store all posts across platforms
    $db->exec("
        CREATE TABLE IF NOT EXISTS social_media_posts (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            account_id INTEGER NOT NULL,
            platform TEXT NOT NULL CHECK (platform IN ('facebook', 'instagram', 'twitter', 'linkedin', 'tiktok', 'youtube')),
            post_id TEXT,
            post_type TEXT DEFAULT 'text' CHECK (post_type IN ('text', 'image', 'video', 'carousel', 'story', 'reel', 'short')),
            content TEXT,
            media_urls TEXT,
            hashtags TEXT,
            mentions TEXT,
            scheduled_at DATETIME,
            published_at DATETIME,
            post_status TEXT DEFAULT 'draft' CHECK (post_status IN ('draft', 'scheduled', 'published', 'failed', 'deleted')),
            engagement_score REAL DEFAULT 0,
            likes_count INTEGER DEFAULT 0,
            comments_count INTEGER DEFAULT 0,
            shares_count INTEGER DEFAULT 0,
            views_count INTEGER DEFAULT 0,
            reach_count INTEGER DEFAULT 0,
            impressions_count INTEGER DEFAULT 0,
            click_count INTEGER DEFAULT 0,
            save_count INTEGER DEFAULT 0,
            ai_generated BOOLEAN DEFAULT FALSE,
            ai_provider TEXT,
            ai_prompt TEXT,
            campaign_id INTEGER,
            ab_test_id INTEGER,
            post_meta TEXT,
            error_message TEXT,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )
    ");
    
    // Social Media Campaigns - Multi-platform campaign management
    $db->exec("
        CREATE TABLE IF NOT EXISTS social_media_campaigns (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            campaign_id INTEGER,
            name TEXT NOT NULL,
            description TEXT,
            objective TEXT DEFAULT 'engagement' CHECK (objective IN ('awareness', 'engagement', 'traffic', 'leads', 'sales', 'app_installs')),
            target_platforms TEXT,
            target_audience TEXT,
            budget_total REAL DEFAULT 0,
            budget_daily REAL DEFAULT 0,
            start_date DATE,
            end_date DATE,
            campaign_status TEXT DEFAULT 'draft' CHECK (campaign_status IN ('draft', 'active', 'paused', 'completed', 'cancelled')),
            content_themes TEXT,
            posting_schedule TEXT,
            hashtag_strategy TEXT,
            kpi_targets TEXT,
            ai_content_enabled BOOLEAN DEFAULT TRUE,
            ai_optimization_enabled BOOLEAN DEFAULT TRUE,
            auto_posting_enabled BOOLEAN DEFAULT FALSE,
            created_by INTEGER,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )
    ");
    
    // Content Calendar - Schedule and plan content
    $db->exec("
        CREATE TABLE IF NOT EXISTS content_calendar (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            title TEXT NOT NULL,
            content_type TEXT DEFAULT 'post' CHECK (content_type IN ('post', 'story', 'reel', 'video', 'carousel', 'live')),
            platforms TEXT NOT NULL,
            content TEXT,
            media_assets TEXT,
            hashtags TEXT,
            scheduled_date DATE NOT NULL,
            scheduled_time TIME,
            content_status TEXT DEFAULT 'planned' CHECK (content_status IN ('planned', 'in_progress', 'ready', 'scheduled', 'published', 'cancelled')),
            assigned_to INTEGER,
            campaign_id INTEGER,
            ai_generated BOOLEAN DEFAULT FALSE,
            ai_brief TEXT,
            approval_status TEXT DEFAULT 'pending' CHECK (approval_status IN ('pending', 'approved', 'rejected', 'revision_needed')),
            approved_by INTEGER,
            approved_at DATETIME,
            notes TEXT,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )
    ");
    
    // Social Media Analytics - Detailed performance tracking
    $db->exec("
        CREATE TABLE IF NOT EXISTS social_media_analytics (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            account_id INTEGER NOT NULL,
            post_id INTEGER,
            platform TEXT NOT NULL CHECK (platform IN ('facebook', 'instagram', 'twitter', 'linkedin', 'tiktok', 'youtube')),
            metric_date DATE NOT NULL,
            metric_hour INTEGER DEFAULT 0,
            followers_count INTEGER DEFAULT 0,
            following_count INTEGER DEFAULT 0,
            posts_count INTEGER DEFAULT 0,
            likes_count INTEGER DEFAULT 0,
            comments_count INTEGER DEFAULT 0,
            shares_count INTEGER DEFAULT 0,
            views_count INTEGER DEFAULT 0,
            reach_count INTEGER DEFAULT 0,
            impressions_count INTEGER DEFAULT 0,
            clicks_count INTEGER DEFAULT 0,
            saves_count INTEGER DEFAULT 0,
            story_views INTEGER DEFAULT 0,
            story_exits INTEGER DEFAULT 0,
            profile_visits INTEGER DEFAULT 0,
            website_clicks INTEGER DEFAULT 0,
            email_contacts INTEGER DEFAULT 0,
            phone_contacts INTEGER DEFAULT 0,
            engagement_rate REAL DEFAULT 0,
            reach_rate REAL DEFAULT 0,
            click_through_rate REAL DEFAULT 0,
            cost_per_click REAL DEFAULT 0,
            cost_per_engagement REAL DEFAULT 0,
            return_on_ad_spend REAL DEFAULT 0,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )
    ");
    
    // Social Media Automation Rules - Smart automation logic
    $db->exec("
        CREATE TABLE IF NOT EXISTS social_media_automation (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            rule_name TEXT NOT NULL,
            rule_type TEXT NOT NULL CHECK (rule_type IN ('auto_post', 'auto_respond', 'auto_engage', 'content_curation', 'hashtag_optimization')),
            platforms TEXT NOT NULL,
            trigger_conditions TEXT,
            action_settings TEXT,
            rule_status TEXT DEFAULT 'active' CHECK (rule_status IN ('active', 'paused', 'disabled')),
            success_count INTEGER DEFAULT 0,
            failure_count INTEGER DEFAULT 0,
            last_executed_at DATETIME,
            next_execution_at DATETIME,
            ai_enabled BOOLEAN DEFAULT TRUE,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )
    ");
    
    echo "✅ Social media management tables created successfully!\n";
}

function migration_006_down($db) {
    echo "Dropping social media management tables...\n";
    
    $db->exec("DROP TABLE IF EXISTS social_media_automation");
    $db->exec("DROP TABLE IF EXISTS social_media_analytics");
    $db->exec("DROP TABLE IF EXISTS content_calendar");
    $db->exec("DROP TABLE IF EXISTS social_media_campaigns");
    $db->exec("DROP TABLE IF EXISTS social_media_posts");
    $db->exec("DROP TABLE IF EXISTS social_media_accounts");
    
    echo "✅ Social media management tables dropped successfully!\n";
}

// Auto-run if called directly
if (basename(__FILE__) === basename($_SERVER['SCRIPT_FILENAME'])) {
    $DB_FILE = __DIR__.'/../crm.sqlite';
    $db = new PDO('sqlite:'.$DB_FILE);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    migration_006_up($db);
}