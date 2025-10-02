<?php
/**
 * Social Media Automation Cron Jobs
 * 
 * This file contains all the scheduled tasks for social media automation.
 * It should be run by a cron job every minute:
 * * * * * * /usr/bin/php /path/to/automation_cron.php
 */

require_once __DIR__ . '/../lib/db.php';
require_once __DIR__ . '/SocialMediaAutomationEngine.php';
require_once __DIR__ . '/ContentCalendarManager.php';

// Prevent multiple instances from running
$lockFile = __DIR__ . '/../logs/automation_cron.lock';
if (file_exists($lockFile)) {
    $lockTime = filemtime($lockFile);
    if (time() - $lockTime < 300) { // 5 minutes
        echo "Automation cron is already running.\n";
        exit;
    }
}
file_put_contents($lockFile, time());

try {
    $automationEngine = new SocialMediaAutomationEngine();
    $calendarManager = new ContentCalendarManager();
    
    echo "🚀 Starting Social Media Automation Cron - " . date('Y-m-d H:i:s') . "\n";
    
    // 1. Process pending automation jobs (every minute)
    echo "\n📋 Processing job queue...\n";
    $processedJobs = $automationEngine->processJobQueue();
    echo "Processed $processedJobs jobs\n";
    
    // 2. Execute automation rules (every 5 minutes)
    if (date('i') % 5 == 0) {
        echo "\n🤖 Executing automation rules...\n";
        $executedRules = $automationEngine->executeAutomationRules();
        echo "Executed $executedRules rules\n";
    }
    
    // 3. Process engagement automation (every 2 minutes)
    if (date('i') % 2 == 0) {
        echo "\n💬 Processing engagement automation...\n";
        $engagementActions = $automationEngine->processEngagementAutomation();
        echo "Processed $engagementActions engagement rules\n";
    }
    
    // 4. Optimize posting times (every hour)
    if (date('i') == 0) {
        echo "\n⏰ Optimizing posting times...\n";
        $optimizedAccounts = $automationEngine->optimizePostingTimes();
        echo "Optimized posting times for $optimizedAccounts accounts\n";
        
        // Auto-optimize scheduled posts
        echo "\n🎯 Auto-optimizing scheduled posts...\n";
        $optimizedPosts = optimizeScheduledPosts($calendarManager);
        echo "Optimized $optimizedPosts scheduled posts\n";
    }
    
    // 5. Sync analytics data (every 30 minutes)
    if (date('i') % 30 == 0) {
        echo "\n📊 Syncing analytics data...\n";
        $syncedAccounts = syncAnalyticsData($automationEngine);
        echo "Synced analytics for $syncedAccounts accounts\n";
    }
    
    // 6. Clean up old data (daily at midnight)
    if (date('H:i') == '00:00') {
        echo "\n🧹 Cleaning up old data...\n";
        cleanupOldData();
        echo "Cleanup completed\n";
    }
    
    // 7. Generate daily reports (daily at 9 AM)
    if (date('H:i') == '09:00') {
        echo "\n📈 Generating daily reports...\n";
        generateDailyReports();
        echo "Daily reports generated\n";
    }
    
    echo "\n✅ Automation cron completed successfully - " . date('Y-m-d H:i:s') . "\n";
    
} catch (Exception $e) {
    echo "\n❌ Error in automation cron: " . $e->getMessage() . "\n";
    error_log("Automation cron error: " . $e->getMessage());
} finally {
    // Remove lock file
    if (file_exists($lockFile)) {
        unlink($lockFile);
    }
}

/**
 * Auto-optimize scheduled posts that have auto_optimize enabled
 */
function optimizeScheduledPosts($calendarManager) {
    global $db;
    
    $stmt = $db->query("
        SELECT id FROM content_calendar_extended 
        WHERE auto_optimize = 1 AND status = 'scheduled'
        AND scheduled_at > datetime('now')
        AND scheduled_at < datetime('now', '+7 days')
    ");
    
    $posts = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $optimized = 0;
    
    foreach ($posts as $post) {
        $result = $calendarManager->autoOptimizePostTiming($post['id']);
        if ($result) {
            $optimized++;
        }
    }
    
    return $optimized;
}

/**
 * Sync analytics data for all connected accounts
 */
function syncAnalyticsData($automationEngine) {
    global $db;
    
    $stmt = $db->query("
        SELECT * FROM social_media_accounts 
        WHERE status = 'active' AND last_sync < datetime('now', '-1 hour')
    ");
    
    $accounts = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $synced = 0;
    
    foreach ($accounts as $account) {
        try {
            // Create analytics sync job
            $jobId = 'sync_' . uniqid();
            $payload = json_encode([
                'account_id' => $account['id'],
                'platform' => $account['platform']
            ]);
            
            $stmt = $db->prepare("
                INSERT INTO automation_job_queue 
                (job_id, job_type, payload, scheduled_at)
                VALUES (?, 'analytics_sync', ?, datetime('now'))
            ");
            
            $stmt->execute([$jobId, $payload]);
            $synced++;
            
        } catch (Exception $e) {
            error_log("Failed to sync analytics for account {$account['id']}: " . $e->getMessage());
        }
    }
    
    return $synced;
}

/**
 * Clean up old completed jobs and logs
 */
function cleanupOldData() {
    global $db;
    
    // Clean up completed jobs older than 30 days
    $db->exec("
        DELETE FROM automation_job_queue 
        WHERE status IN ('completed', 'failed') 
        AND completed_at < datetime('now', '-30 days')
    ");
    
    // Clean up old automation logs
    $db->exec("
        DELETE FROM automation_logs 
        WHERE created_at < datetime('now', '-60 days')
    ");
    
    // Clean up old published calendar entries
    $db->exec("
        DELETE FROM content_calendar_extended 
        WHERE status = 'published' 
        AND published_at < datetime('now', '-90 days')
    ");
    
    echo "Cleaned up old jobs, logs, and published content\n";
}

/**
 * Generate daily performance reports
 */
function generateDailyReports() {
    global $db;
    
    $yesterday = date('Y-m-d', strtotime('-1 day'));
    
    // Get posting stats
    $stmt = $db->prepare("
        SELECT platform, COUNT(*) as posts_count, 
               AVG(engagement_rate) as avg_engagement,
               SUM(reach) as total_reach
        FROM social_media_posts 
        WHERE DATE(published_at) = ?
        GROUP BY platform
    ");
    $stmt->execute([$yesterday]);
    $stats = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Generate report
    $report = [
        'date' => $yesterday,
        'platform_stats' => $stats,
        'total_posts' => array_sum(array_column($stats, 'posts_count')),
        'avg_engagement' => array_sum(array_column($stats, 'avg_engagement')) / count($stats),
        'total_reach' => array_sum(array_column($stats, 'total_reach')),
        'generated_at' => date('Y-m-d H:i:s')
    ];
    
    // Save report (in a real app, you might email this or save to a reports table)
    $reportFile = __DIR__ . "/../logs/daily_report_$yesterday.json";
    file_put_contents($reportFile, json_encode($report, JSON_PRETTY_PRINT));
    
    echo "Daily report saved to $reportFile\n";
}

// If running from command line
if (php_sapi_name() === 'cli') {
    // Script is already executed above
}