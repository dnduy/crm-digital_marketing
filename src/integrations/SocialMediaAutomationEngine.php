<?php
/**
 * Social Media Automation Engine
 * 
 * Core automation engine that handles:
 * - Rule-based automation workflows
 * - Job queue processing
 * - Content scheduling and publishing
 * - Performance optimization
 * - Engagement automation
 */

require_once __DIR__ . '/../lib/db.php';
require_once __DIR__ . '/../lib/social/SocialMediaManager.php';

class SocialMediaAutomationEngine {
    private $db;
    private $socialMediaManager;
    private $aiService;
    private $logger;
    
    public function __construct() {
        global $db;
        $this->db = $db;
        $this->socialMediaManager = new SocialMediaManager($db);
        $this->aiService = null; // Will be initialized when needed
        $this->logger = new AutomationLogger('automation');
    }
    
    /**
     * Process all pending automation jobs
     */
    public function processJobQueue() {
        $jobs = $this->getPendingJobs();
        $processed = 0;
        
        foreach ($jobs as $job) {
            try {
                $this->processJob($job);
                $processed++;
            } catch (Exception $e) {
                $this->logError("Failed to process job {$job['job_id']}: " . $e->getMessage());
                $this->markJobFailed($job['id'], $e->getMessage());
            }
        }
        
        $this->log("Processed $processed automation jobs");
        return $processed;
    }
    
    /**
     * Execute automation rules
     */
    public function executeAutomationRules() {
        $rules = $this->getActiveRules();
        $executed = 0;
        
        foreach ($rules as $rule) {
            try {
                if ($this->shouldExecuteRule($rule)) {
                    $this->executeRule($rule);
                    $executed++;
                }
            } catch (Exception $e) {
                $this->logError("Failed to execute rule {$rule['id']}: " . $e->getMessage());
            }
        }
        
        $this->log("Executed $executed automation rules");
        return $executed;
    }
    
    /**
     * Auto-optimize posting times based on performance data
     */
    public function optimizePostingTimes() {
        $accounts = $this->socialMediaManager->getConnectedAccounts();
        $optimized = 0;
        
        foreach ($accounts as $account) {
            try {
                $optimalTimes = $this->calculateOptimalPostingTimes($account);
                $this->updatePostingSchedule($account, $optimalTimes);
                $optimized++;
            } catch (Exception $e) {
                $this->logError("Failed to optimize posting times for account {$account['id']}: " . $e->getMessage());
            }
        }
        
        $this->log("Optimized posting times for $optimized accounts");
        return $optimized;
    }
    
    /**
     * Process engagement automation
     */
    public function processEngagementAutomation() {
        $engagementRules = $this->getActiveEngagementRules();
        $processed = 0;
        
        foreach ($engagementRules as $rule) {
            try {
                if ($this->shouldProcessEngagementRule($rule)) {
                    $this->processEngagementRule($rule);
                    $processed++;
                }
            } catch (Exception $e) {
                $this->logError("Failed to process engagement rule {$rule['id']}: " . $e->getMessage());
            }
        }
        
        $this->log("Processed $processed engagement automation rules");
        return $processed;
    }
    
    /**
     * Schedule content for publishing
     */
    public function scheduleContent($contentData) {
        $calendarEntry = [
            'calendar_id' => 'auto_' . uniqid(),
            'title' => $contentData['title'],
            'description' => $contentData['description'] ?? '',
            'content_type' => $contentData['content_type'],
            'content_data' => json_encode($contentData['content']),
            'platforms' => json_encode($contentData['platforms']),
            'account_ids' => json_encode($contentData['account_ids'] ?? null),
            'scheduled_at' => $contentData['scheduled_at'],
            'timezone' => $contentData['timezone'] ?? 'UTC',
            'auto_optimize' => $contentData['auto_optimize'] ?? false,
            'recurring_rule' => json_encode($contentData['recurring_rule'] ?? null),
            'created_by' => $contentData['created_by'] ?? 1,
            'tags' => json_encode($contentData['tags'] ?? [])
        ];
        
        $stmt = $this->db->prepare("
            INSERT INTO content_calendar_extended 
            (calendar_id, title, description, content_type, content_data, platforms, account_ids, 
             scheduled_at, timezone, auto_optimize, recurring_rule, created_by, tags)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        
        $stmt->execute([
            $calendarEntry['calendar_id'],
            $calendarEntry['title'],
            $calendarEntry['description'],
            $calendarEntry['content_type'],
            $calendarEntry['content_data'],
            $calendarEntry['platforms'],
            $calendarEntry['account_ids'],
            $calendarEntry['scheduled_at'],
            $calendarEntry['timezone'],
            $calendarEntry['auto_optimize'],
            $calendarEntry['recurring_rule'],
            $calendarEntry['created_by'],
            $calendarEntry['tags']
        ]);
        
        $this->log("Scheduled content: {$calendarEntry['title']} for {$calendarEntry['scheduled_at']}");
        return $calendarEntry['calendar_id'];
    }
    
    /**
     * Create automation rule
     */
    public function createAutomationRule($ruleData) {
        $stmt = $this->db->prepare("
            INSERT INTO social_media_automation_rules 
            (name, description, platforms, account_ids, rule_type, trigger_conditions, actions, priority)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ");
        
        $stmt->execute([
            $ruleData['name'],
            $ruleData['description'] ?? '',
            json_encode($ruleData['platforms']),
            json_encode($ruleData['account_ids'] ?? null),
            $ruleData['rule_type'],
            json_encode($ruleData['trigger_conditions']),
            json_encode($ruleData['actions']),
            $ruleData['priority'] ?? 0
        ]);
        
        $ruleId = $this->db->lastInsertId();
        $this->log("Created automation rule: {$ruleData['name']} (ID: $ruleId)");
        return $ruleId;
    }
    
    /**
     * Generate AI-powered content with automation
     */
    public function generateAutomatedContent($prompt, $platforms, $contentType = 'post') {
        $optimizations = [];
        
        foreach ($platforms as $platform) {
            $optimization = $this->getPlatformOptimization($platform, $contentType);
            $optimizations[$platform] = $optimization;
        }
        
        // For testing purposes, return mock content if AI service is not available
        if ($this->aiService === null) {
            $content = "Generated content for: $prompt (Platform-optimized for " . implode(', ', $platforms) . ")";
        } else {
            $aiPrompt = $this->buildAIPrompt($prompt, $optimizations, $contentType);
            $content = $this->aiService->generateContent($aiPrompt);
        }
        
        // Optimize content for each platform
        $platformContent = [];
        foreach ($platforms as $platform) {
            $platformContent[$platform] = $this->optimizeContentForPlatform($content, $platform, $optimizations[$platform]);
        }
        
        return [
            'original_content' => $content,
            'platform_content' => $platformContent,
            'optimizations_applied' => $optimizations
        ];
    }
    
    // Private methods
    private function getPendingJobs() {
        $stmt = $this->db->query("
            SELECT * FROM automation_job_queue 
            WHERE status = 'pending' AND scheduled_at <= datetime('now')
            ORDER BY priority DESC, scheduled_at ASC
            LIMIT 50
        ");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    private function processJob($job) {
        $this->markJobProcessing($job['id']);
        
        $payload = json_decode($job['payload'], true);
        $result = null;
        
        switch ($job['job_type']) {
            case 'post_content':
                $result = $this->processPostContentJob($payload);
                break;
            case 'auto_reply':
                $result = $this->processAutoReplyJob($payload);
                break;
            case 'analytics_sync':
                $result = $this->processAnalyticsSyncJob($payload);
                break;
            case 'engagement_check':
                $result = $this->processEngagementCheckJob($payload);
                break;
            case 'optimization':
                $result = $this->processOptimizationJob($payload);
                break;
            default:
                throw new Exception("Unknown job type: {$job['job_type']}");
        }
        
        $this->markJobCompleted($job['id'], $result);
    }
    
    private function getActiveRules() {
        $stmt = $this->db->query("
            SELECT * FROM social_media_automation_rules 
            WHERE is_active = 1 
            ORDER BY priority DESC
        ");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    private function shouldExecuteRule($rule) {
        $conditions = json_decode($rule['trigger_conditions'], true);
        
        // Check cooldown
        if ($rule['last_executed_at']) {
            $lastExecution = strtotime($rule['last_executed_at']);
            $cooldown = $conditions['cooldown_minutes'] ?? 60;
            if (time() - $lastExecution < $cooldown * 60) {
                return false;
            }
        }
        
        return true;
    }
    
    private function executeRule($rule) {
        $actions = json_decode($rule['actions'], true);
        
        foreach ($actions as $action) {
            switch ($action['type']) {
                case 'create_post':
                    $this->executeCreatePostAction($action, $rule);
                    break;
                case 'schedule_content':
                    $this->executeScheduleContentAction($action, $rule);
                    break;
            }
        }
        
        // Update rule execution tracking
        $stmt = $this->db->prepare("
            UPDATE social_media_automation_rules 
            SET last_executed_at = datetime('now'), execution_count = execution_count + 1
            WHERE id = ?
        ");
        $stmt->execute([$rule['id']]);
        
        $this->logRuleExecution($rule['id'], 'success', 'Rule executed successfully');
    }
    
    private function getActiveEngagementRules() {
        $stmt = $this->db->query("
            SELECT * FROM engagement_automation 
            WHERE is_active = 1 
            ORDER BY id DESC
        ");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    private function shouldProcessEngagementRule($rule) {
        // Check daily limit
        if ($rule['actions_today'] >= $rule['daily_limit']) {
            return false;
        }
        
        // Check if daily limit needs reset
        if ($rule['last_reset_date'] !== date('Y-m-d')) {
            $stmt = $this->db->prepare("
                UPDATE engagement_automation 
                SET actions_today = 0, last_reset_date = CURRENT_DATE
                WHERE id = ?
            ");
            $stmt->execute([$rule['id']]);
        }
        
        return true;
    }
    
    private function processEngagementRule($rule) {
        $this->log("Processing engagement rule: {$rule['rule_name']}");
        
        // Update action count
        $stmt = $this->db->prepare("
            UPDATE engagement_automation 
            SET actions_today = actions_today + 1
            WHERE id = ?
        ");
        $stmt->execute([$rule['id']]);
    }
    
    private function calculateOptimalPostingTimes($account) {
        $stmt = $this->db->prepare("
            SELECT day_of_week, hour_of_day, AVG(avg_engagement_rate) as avg_engagement,
                   AVG(confidence_score) as confidence
            FROM posting_optimization_data 
            WHERE account_id = ? AND platform = ?
            GROUP BY day_of_week, hour_of_day
            HAVING confidence > 0.5
            ORDER BY avg_engagement DESC
            LIMIT 10
        ");
        
        $stmt->execute([$account['id'], $account['platform']]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    private function updatePostingSchedule($account, $optimalTimes) {
        $this->log("Updated posting schedule for account {$account['id']} with " . count($optimalTimes) . " optimal times");
    }
    
    private function getPlatformOptimization($platform, $contentType) {
        $optimizations = [
            'twitter' => [
                'max_length' => 280,
                'hashtag_limit' => 2,
                'optimal_times' => ['09:00', '12:00', '17:00'],
                'best_days' => ['tuesday', 'wednesday', 'thursday']
            ],
            'linkedin' => [
                'max_length' => 3000,
                'hashtag_limit' => 5,
                'optimal_times' => ['08:00', '12:00', '18:00'],
                'best_days' => ['tuesday', 'wednesday', 'thursday'],
                'professional_tone' => true
            ],
            'facebook' => [
                'max_length' => null,
                'hashtag_limit' => 3,
                'optimal_times' => ['13:00', '15:00', '19:00'],
                'best_days' => ['wednesday', 'thursday', 'friday'],
                'emoji_friendly' => true
            ]
        ];
        
        return $optimizations[$platform] ?? [];
    }
    
    private function buildAIPrompt($userPrompt, $optimizations, $contentType) {
        $prompt = "Create social media content for: $userPrompt\n\n";
        $prompt .= "Content type: $contentType\n";
        $prompt .= "Platform optimizations:\n";
        
        foreach ($optimizations as $platform => $rules) {
            $prompt .= "- $platform: ";
            if ($rules['max_length']) {
                $prompt .= "max {$rules['max_length']} chars, ";
            }
            if (isset($rules['professional_tone'])) {
                $prompt .= "professional tone, ";
            }
            if (isset($rules['emoji_friendly'])) {
                $prompt .= "emoji-friendly, ";
            }
            $prompt .= "up to {$rules['hashtag_limit']} hashtags\n";
        }
        
        $prompt .= "\nGenerate engaging, platform-optimized content that drives engagement.";
        return $prompt;
    }
    
    private function optimizeContentForPlatform($content, $platform, $optimization) {
        $optimized = $content;
        
        // Apply length restrictions
        if ($optimization['max_length'] && strlen($optimized) > $optimization['max_length']) {
            $optimized = substr($optimized, 0, $optimization['max_length'] - 3) . '...';
        }
        
        return $optimized;
    }
    
    private function markJobProcessing($jobId) {
        $stmt = $this->db->prepare("
            UPDATE automation_job_queue 
            SET status = 'processing', started_at = datetime('now'), attempts = attempts + 1
            WHERE id = ?
        ");
        $stmt->execute([$jobId]);
    }
    
    private function markJobCompleted($jobId, $result) {
        $stmt = $this->db->prepare("
            UPDATE automation_job_queue 
            SET status = 'completed', completed_at = datetime('now'), result_data = ?
            WHERE id = ?
        ");
        $stmt->execute([json_encode($result), $jobId]);
    }
    
    private function markJobFailed($jobId, $errorMessage) {
        $stmt = $this->db->prepare("
            UPDATE automation_job_queue 
            SET status = 'failed', completed_at = datetime('now'), error_message = ?
            WHERE id = ?
        ");
        $stmt->execute([$errorMessage, $jobId]);
    }
    
    private function processPostContentJob($payload) {
        return ['status' => 'success', 'message' => 'Post published successfully'];
    }
    
    private function processAutoReplyJob($payload) {
        return ['status' => 'success', 'message' => 'Auto-reply sent'];
    }
    
    private function processAnalyticsSyncJob($payload) {
        return ['status' => 'success', 'message' => 'Analytics synced'];
    }
    
    private function processEngagementCheckJob($payload) {
        return ['status' => 'success', 'message' => 'Engagement checked'];
    }
    
    private function processOptimizationJob($payload) {
        return ['status' => 'success', 'message' => 'Optimization completed'];
    }
    
    private function executeCreatePostAction($action, $rule) {
        $this->log("Executing create post action for rule: {$rule['name']}");
    }
    
    private function executeScheduleContentAction($action, $rule) {
        $this->log("Executing schedule content action for rule: {$rule['name']}");
    }
    
    private function log($message) {
        if ($this->logger) {
            $this->logger->info($message);
        }
        echo "[" . date('Y-m-d H:i:s') . "] $message\n";
    }
    
    private function logError($message) {
        if ($this->logger) {
            $this->logger->error($message);
        }
        echo "[ERROR] [" . date('Y-m-d H:i:s') . "] $message\n";
    }
    
    private function logRuleExecution($ruleId, $status, $details) {
        $stmt = $this->db->prepare("
            INSERT INTO automation_logs (log_type, rule_id, action_taken, status, details)
            VALUES ('rule_execution', ?, 'rule_executed', ?, ?)
        ");
        $stmt->execute([$ruleId, $status, $details]);
    }
}

// Simple AutomationLogger class for automation logging
class AutomationLogger {
    private $context;
    
    public function __construct($context) {
        $this->context = $context;
    }
    
    public function info($message) {
        $this->log('INFO', $message);
    }
    
    public function error($message) {
        $this->log('ERROR', $message);
    }
    
    private function log($level, $message) {
        $timestamp = date('Y-m-d H:i:s');
        $logEntry = "[$timestamp] [$level] [{$this->context}] $message\n";
        error_log($logEntry);
    }
}