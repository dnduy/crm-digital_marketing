<?php
/**
 * Migration: Create Advanced Social Analytics Tables
 * 
 * Creates tables for comprehensive social media analytics including:
 * - Sentiment analysis tracking
 * - Competitor monitoring
 * - ROI attribution and revenue tracking
 * - Performance prediction models
 * - Advanced reporting and insights
 */

require_once __DIR__ . '/../lib/db.php';

function createAdvancedAnalyticsTables() {
    global $db;
    
    try {
        $db->beginTransaction();
        
        // 1. Sentiment Analysis Table
        $db->exec("
            CREATE TABLE IF NOT EXISTS sentiment_analysis (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                content_id INTEGER NULL,
                post_id INTEGER NULL,
                comment_id INTEGER NULL,
                platform VARCHAR(50) NOT NULL,
                content_text TEXT NOT NULL,
                sentiment_score REAL NOT NULL, -- -1.0 (very negative) to 1.0 (very positive)
                sentiment_label VARCHAR(20) NOT NULL CHECK (sentiment_label IN ('very_negative', 'negative', 'neutral', 'positive', 'very_positive')),
                confidence_score REAL NOT NULL DEFAULT 0.0, -- 0.0 to 1.0
                emotions TEXT, -- JSON array of detected emotions [joy, anger, fear, sadness, surprise, disgust]
                keywords TEXT, -- JSON array of sentiment-driving keywords
                analysis_provider VARCHAR(50) DEFAULT 'internal',
                analyzed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (post_id) REFERENCES social_media_posts(id) ON DELETE SET NULL
            )
        ");
        
        // 2. Competitor Tracking Table
        $db->exec("
            CREATE TABLE IF NOT EXISTS competitor_tracking (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                competitor_name VARCHAR(255) NOT NULL,
                platform VARCHAR(50) NOT NULL,
                username VARCHAR(255) NOT NULL,
                profile_url TEXT,
                follower_count INTEGER DEFAULT 0,
                following_count INTEGER DEFAULT 0,
                post_count INTEGER DEFAULT 0,
                engagement_rate REAL DEFAULT 0.0,
                avg_likes INTEGER DEFAULT 0,
                avg_comments INTEGER DEFAULT 0,
                avg_shares INTEGER DEFAULT 0,
                posting_frequency REAL DEFAULT 0.0, -- posts per day
                content_categories TEXT, -- JSON array of content types
                hashtag_strategy TEXT, -- JSON array of commonly used hashtags
                posting_times TEXT, -- JSON array of optimal posting times
                competitive_score REAL DEFAULT 0.0, -- Overall competitive strength 0-100
                industry VARCHAR(100),
                is_active BOOLEAN DEFAULT 1,
                last_analyzed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                UNIQUE(platform, username)
            )
        ");
        
        // 3. Competitor Posts Tracking
        $db->exec("
            CREATE TABLE IF NOT EXISTS competitor_posts (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                competitor_id INTEGER NOT NULL,
                platform_post_id VARCHAR(255) NOT NULL,
                content_text TEXT,
                content_type VARCHAR(50) DEFAULT 'post',
                media_urls TEXT, -- JSON array of media URLs
                hashtags TEXT, -- JSON array of hashtags
                mentions TEXT, -- JSON array of mentions
                likes_count INTEGER DEFAULT 0,
                comments_count INTEGER DEFAULT 0,
                shares_count INTEGER DEFAULT 0,
                engagement_rate REAL DEFAULT 0.0,
                reach INTEGER DEFAULT 0,
                impressions INTEGER DEFAULT 0,
                sentiment_score REAL DEFAULT 0.0,
                posted_at TIMESTAMP NOT NULL,
                analyzed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (competitor_id) REFERENCES competitor_tracking(id) ON DELETE CASCADE,
                UNIQUE(competitor_id, platform_post_id)
            )
        ");
        
        // 4. ROI Attribution Table
        $db->exec("
            CREATE TABLE IF NOT EXISTS social_roi_attribution (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                campaign_id INTEGER NULL,
                post_id INTEGER NULL,
                platform VARCHAR(50) NOT NULL,
                attribution_model VARCHAR(50) DEFAULT 'last_click', -- first_click, last_click, linear, time_decay, position_based
                traffic_source VARCHAR(100),
                utm_source VARCHAR(100),
                utm_medium VARCHAR(100), 
                utm_campaign VARCHAR(100),
                utm_content VARCHAR(100),
                utm_term VARCHAR(100),
                sessions INTEGER DEFAULT 0,
                page_views INTEGER DEFAULT 0,
                bounce_rate REAL DEFAULT 0.0,
                avg_session_duration INTEGER DEFAULT 0, -- seconds
                conversions INTEGER DEFAULT 0,
                conversion_rate REAL DEFAULT 0.0,
                revenue DECIMAL(10,2) DEFAULT 0.00,
                cost DECIMAL(10,2) DEFAULT 0.00,
                roi_percentage REAL DEFAULT 0.0,
                roas REAL DEFAULT 0.0, -- Return on Ad Spend
                customer_lifetime_value DECIMAL(10,2) DEFAULT 0.00,
                attribution_date DATE NOT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (post_id) REFERENCES social_media_posts(id) ON DELETE SET NULL
            )
        ");
        
        // 5. Performance Prediction Models
        $db->exec("
            CREATE TABLE IF NOT EXISTS performance_predictions (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                model_name VARCHAR(100) NOT NULL,
                model_type VARCHAR(50) NOT NULL CHECK (model_type IN ('engagement', 'reach', 'conversion', 'revenue', 'sentiment')),
                platform VARCHAR(50) NOT NULL,
                account_id INTEGER NULL,
                input_features TEXT NOT NULL, -- JSON object with model features
                prediction_value REAL NOT NULL,
                confidence_interval TEXT, -- JSON object with min/max confidence bounds
                confidence_score REAL DEFAULT 0.0,
                prediction_date DATE NOT NULL,
                prediction_horizon INTEGER DEFAULT 7, -- days ahead
                actual_value REAL NULL, -- filled when actual data becomes available
                accuracy_score REAL NULL, -- calculated when actual vs predicted
                model_version VARCHAR(20) DEFAULT '1.0',
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                validated_at TIMESTAMP NULL,
                FOREIGN KEY (account_id) REFERENCES social_media_accounts(id) ON DELETE SET NULL
            )
        ");
        
        // 6. Advanced Analytics Reports
        $db->exec("
            CREATE TABLE IF NOT EXISTS analytics_reports (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                report_name VARCHAR(255) NOT NULL,
                report_type VARCHAR(50) NOT NULL CHECK (report_type IN ('daily', 'weekly', 'monthly', 'quarterly', 'custom', 'competitor', 'roi', 'sentiment')),
                report_scope VARCHAR(50) DEFAULT 'all', -- all, platform_specific, campaign_specific
                scope_filters TEXT, -- JSON object with filter criteria
                data_summary TEXT NOT NULL, -- JSON object with key metrics
                insights TEXT, -- JSON array of AI-generated insights
                recommendations TEXT, -- JSON array of actionable recommendations
                performance_score REAL DEFAULT 0.0, -- Overall performance score 0-100
                trend_direction VARCHAR(20) DEFAULT 'stable', -- improving, declining, stable
                report_period_start DATE NOT NULL,
                report_period_end DATE NOT NULL,
                generated_by INTEGER DEFAULT 1, -- user ID who generated report
                is_automated BOOLEAN DEFAULT 0,
                report_data TEXT, -- JSON object with detailed report data
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )
        ");
        
        // 7. Social Media Insights
        $db->exec("
            CREATE TABLE IF NOT EXISTS social_media_insights (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                insight_type VARCHAR(50) NOT NULL CHECK (insight_type IN ('trend', 'anomaly', 'opportunity', 'warning', 'recommendation')),
                platform VARCHAR(50),
                account_id INTEGER NULL,
                post_id INTEGER NULL,
                title VARCHAR(255) NOT NULL,
                description TEXT NOT NULL,
                severity VARCHAR(20) DEFAULT 'medium' CHECK (severity IN ('low', 'medium', 'high', 'critical')),
                confidence_score REAL DEFAULT 0.0,
                impact_score REAL DEFAULT 0.0, -- potential impact 0-100
                data_points TEXT, -- JSON object with supporting data
                suggested_actions TEXT, -- JSON array of recommended actions
                is_actionable BOOLEAN DEFAULT 1,
                is_read BOOLEAN DEFAULT 0,
                expires_at TIMESTAMP NULL, -- when insight becomes irrelevant
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                acted_upon_at TIMESTAMP NULL,
                FOREIGN KEY (account_id) REFERENCES social_media_accounts(id) ON DELETE CASCADE,
                FOREIGN KEY (post_id) REFERENCES social_media_posts(id) ON DELETE CASCADE
            )
        ");
        
        // 8. Analytics Dashboard Configurations
        $db->exec("
            CREATE TABLE IF NOT EXISTS analytics_dashboards (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                dashboard_name VARCHAR(255) NOT NULL,
                user_id INTEGER NOT NULL DEFAULT 1,
                layout_config TEXT NOT NULL, -- JSON object with widget layout
                widget_configs TEXT NOT NULL, -- JSON array of widget configurations
                filters TEXT, -- JSON object with default filters
                refresh_interval INTEGER DEFAULT 300, -- seconds
                is_default BOOLEAN DEFAULT 0,
                is_shared BOOLEAN DEFAULT 0,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )
        ");
        
        // Create indexes for performance
        $indexes = [
            "CREATE INDEX IF NOT EXISTS idx_sentiment_platform_date ON sentiment_analysis(platform, analyzed_at)",
            "CREATE INDEX IF NOT EXISTS idx_sentiment_score ON sentiment_analysis(sentiment_score)",
            "CREATE INDEX IF NOT EXISTS idx_competitor_platform ON competitor_tracking(platform, is_active)",
            "CREATE INDEX IF NOT EXISTS idx_competitor_posts_date ON competitor_posts(posted_at, competitor_id)",
            "CREATE INDEX IF NOT EXISTS idx_roi_attribution_date ON social_roi_attribution(attribution_date, platform)",
            "CREATE INDEX IF NOT EXISTS idx_roi_campaign ON social_roi_attribution(campaign_id, utm_campaign)",
            "CREATE INDEX IF NOT EXISTS idx_predictions_date ON performance_predictions(prediction_date, platform)",
            "CREATE INDEX IF NOT EXISTS idx_predictions_type ON performance_predictions(model_type, platform)",
            "CREATE INDEX IF NOT EXISTS idx_reports_type_date ON analytics_reports(report_type, created_at)",
            "CREATE INDEX IF NOT EXISTS idx_insights_type_severity ON social_media_insights(insight_type, severity, is_read)"
        ];
        
        foreach ($indexes as $index) {
            $db->exec($index);
        }
        
        $db->commit();
        echo "✅ Successfully created advanced analytics tables:\n";
        echo "   - sentiment_analysis\n";
        echo "   - competitor_tracking\n";
        echo "   - competitor_posts\n";
        echo "   - social_roi_attribution\n";
        echo "   - performance_predictions\n";
        echo "   - analytics_reports\n";
        echo "   - social_media_insights\n";
        echo "   - analytics_dashboards\n";
        echo "   - Created 10 performance indexes\n";
        
    } catch (Exception $e) {
        $db->rollBack();
        die("❌ Error creating advanced analytics tables: " . $e->getMessage() . "\n");
    }
}

// Run the migration
if (php_sapi_name() === 'cli') {
    echo "🚀 Running Advanced Analytics Tables Migration...\n";
    createAdvancedAnalyticsTables();
}