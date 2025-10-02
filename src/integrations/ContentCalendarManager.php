<?php
/**
 * Content Calendar Manager
 * 
 * Manages content scheduling, calendar views, and publishing workflows
 */

require_once __DIR__ . '/../lib/db.php';
require_once __DIR__ . '/SocialMediaAutomationEngine.php';

class ContentCalendarManager {
    private $db;
    private $automationEngine;
    
    public function __construct() {
        global $db;
        $this->db = $db;
        $this->automationEngine = new SocialMediaAutomationEngine();
    }
    
    /**
     * Get calendar entries for a date range
     */
    public function getCalendarEntries($startDate, $endDate, $platforms = null, $status = null) {
        $sql = "
            SELECT * FROM content_calendar_extended 
            WHERE scheduled_at BETWEEN ? AND ?
        ";
        $params = [$startDate, $endDate];
        
        if ($platforms) {
            $platformConditions = [];
            foreach ($platforms as $platform) {
                $platformConditions[] = "platforms LIKE ?";
                $params[] = '%"' . $platform . '"%';
            }
            $sql .= " AND (" . implode(' OR ', $platformConditions) . ")";
        }
        
        if ($status) {
            $sql .= " AND status = ?";
            $params[] = $status;
        }
        
        $sql .= " ORDER BY scheduled_at ASC";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        
        $entries = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Parse JSON fields
        foreach ($entries as &$entry) {
            $entry['platforms'] = json_decode($entry['platforms'], true);
            $entry['content_data'] = json_decode($entry['content_data'], true);
            $entry['account_ids'] = json_decode($entry['account_ids'], true);
            $entry['recurring_rule'] = json_decode($entry['recurring_rule'], true);
            $entry['tags'] = json_decode($entry['tags'], true);
        }
        
        return $entries;
    }
    
    /**
     * Create new calendar entry
     */
    public function createCalendarEntry($data) {
        $calendarId = 'cal_' . uniqid();
        
        $stmt = $this->db->prepare("
            INSERT INTO content_calendar_extended 
            (calendar_id, title, description, content_type, content_data, platforms, account_ids,
             scheduled_at, timezone, auto_optimize, recurring_rule, approval_required, created_by, tags)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        
        $stmt->execute([
            $calendarId,
            $data['title'],
            $data['description'] ?? '',
            $data['content_type'],
            json_encode($data['content_data']),
            json_encode($data['platforms']),
            json_encode($data['account_ids'] ?? null),
            $data['scheduled_at'],
            $data['timezone'] ?? 'UTC',
            $data['auto_optimize'] ?? false,
            json_encode($data['recurring_rule'] ?? null),
            $data['approval_required'] ?? false,
            $data['created_by'] ?? 1,
            json_encode($data['tags'] ?? [])
        ]);
        
        $entryId = $this->db->lastInsertId();
        
        // Handle recurring posts
        if (!empty($data['recurring_rule'])) {
            $this->createRecurringEntries($entryId, $data);
        }
        
        // Create automation job if scheduled
        if ($data['scheduled_at'] > date('Y-m-d H:i:s')) {
            $this->createPostingJob($entryId);
        }
        
        return $entryId;
    }
    
    /**
     * Update calendar entry
     */
    public function updateCalendarEntry($id, $data) {
        $stmt = $this->db->prepare("
            UPDATE content_calendar_extended 
            SET title = ?, description = ?, content_type = ?, content_data = ?,
                platforms = ?, account_ids = ?, scheduled_at = ?, timezone = ?,
                auto_optimize = ?, recurring_rule = ?, tags = ?, updated_at = datetime('now')
            WHERE id = ?
        ");
        
        return $stmt->execute([
            $data['title'],
            $data['description'] ?? '',
            $data['content_type'],
            json_encode($data['content_data']),
            json_encode($data['platforms']),
            json_encode($data['account_ids'] ?? null),
            $data['scheduled_at'],
            $data['timezone'] ?? 'UTC',
            $data['auto_optimize'] ?? false,
            json_encode($data['recurring_rule'] ?? null),
            json_encode($data['tags'] ?? []),
            $id
        ]);
    }
    
    /**
     * Delete calendar entry
     */
    public function deleteCalendarEntry($id) {
        // Cancel associated automation jobs
        $stmt = $this->db->prepare("
            UPDATE automation_job_queue 
            SET status = 'cancelled' 
            WHERE job_type = 'post_content' AND JSON_EXTRACT(payload, '$.content_id') = ?
        ");
        $stmt->execute([$id]);
        
        // Delete the calendar entry
        $stmt = $this->db->prepare("DELETE FROM content_calendar_extended WHERE id = ?");
        return $stmt->execute([$id]);
    }
    
    /**
     * Approve calendar entry
     */
    public function approveCalendarEntry($id, $approvedBy) {
        $stmt = $this->db->prepare("
            UPDATE content_calendar_extended 
            SET approved_by = ?, approved_at = datetime('now')
            WHERE id = ?
        ");
        
        return $stmt->execute([$approvedBy, $id]);
    }
    
    /**
     * Get calendar view for a specific month
     */
    public function getMonthlyCalendarView($year, $month, $platforms = null) {
        $startDate = "$year-$month-01 00:00:00";
        $endDate = date('Y-m-t 23:59:59', strtotime($startDate));
        
        $entries = $this->getCalendarEntries($startDate, $endDate, $platforms);
        
        // Group by date
        $calendar = [];
        foreach ($entries as $entry) {
            $date = date('Y-m-d', strtotime($entry['scheduled_at']));
            if (!isset($calendar[$date])) {
                $calendar[$date] = [];
            }
            $calendar[$date][] = $entry;
        }
        
        return $calendar;
    }
    
    /**
     * Get weekly calendar view
     */
    public function getWeeklyCalendarView($startDate, $platforms = null) {
        $endDate = date('Y-m-d 23:59:59', strtotime($startDate . ' +6 days'));
        $startDate = date('Y-m-d 00:00:00', strtotime($startDate));
        
        $entries = $this->getCalendarEntries($startDate, $endDate, $platforms);
        
        // Group by date and hour
        $calendar = [];
        for ($i = 0; $i < 7; $i++) {
            $date = date('Y-m-d', strtotime($startDate . " +$i days"));
            $calendar[$date] = [];
        }
        
        foreach ($entries as $entry) {
            $date = date('Y-m-d', strtotime($entry['scheduled_at']));
            $hour = date('H', strtotime($entry['scheduled_at']));
            
            if (!isset($calendar[$date][$hour])) {
                $calendar[$date][$hour] = [];
            }
            $calendar[$date][$hour][] = $entry;
        }
        
        return $calendar;
    }
    
    /**
     * Bulk schedule multiple posts
     */
    public function bulkSchedule($posts, $schedule) {
        $scheduled = [];
        
        foreach ($posts as $index => $post) {
            $scheduledTime = $this->calculateScheduledTime($schedule, $index);
            
            $data = array_merge($post, [
                'scheduled_at' => $scheduledTime,
                'auto_optimize' => $schedule['auto_optimize'] ?? false
            ]);
            
            $entryId = $this->createCalendarEntry($data);
            $scheduled[] = [
                'id' => $entryId,
                'scheduled_at' => $scheduledTime,
                'title' => $post['title']
            ];
        }
        
        return $scheduled;
    }
    
    /**
     * Get optimal posting times for platforms
     */
    public function getOptimalPostingTimes($platforms, $accountIds = null) {
        $optimalTimes = [];
        
        foreach ($platforms as $platform) {
            $sql = "
                SELECT day_of_week, hour_of_day, AVG(avg_engagement_rate) as avg_engagement
                FROM posting_optimization_data 
                WHERE platform = ?
            ";
            $params = [$platform];
            
            if ($accountIds) {
                $placeholders = str_repeat('?,', count($accountIds) - 1) . '?';
                $sql .= " AND account_id IN ($placeholders)";
                $params = array_merge($params, $accountIds);
            }
            
            $sql .= "
                GROUP BY day_of_week, hour_of_day
                HAVING COUNT(*) >= 3
                ORDER BY avg_engagement DESC
                LIMIT 5
            ";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
            
            $optimalTimes[$platform] = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }
        
        return $optimalTimes;
    }
    
    /**
     * Auto-optimize scheduled post timing
     */
    public function autoOptimizePostTiming($entryId) {
        $entry = $this->getCalendarEntry($entryId);
        if (!$entry || !$entry['auto_optimize']) {
            return false;
        }
        
        $platforms = json_decode($entry['platforms'], true);
        $accountIds = json_decode($entry['account_ids'], true);
        
        $optimalTimes = $this->getOptimalPostingTimes($platforms, $accountIds);
        
        if (empty($optimalTimes)) {
            return false;
        }
        
        // Find the best time across all platforms
        $bestTime = $this->findBestCrossplatformTime($optimalTimes, $entry['scheduled_at']);
        
        if ($bestTime) {
            $stmt = $this->db->prepare("
                UPDATE content_calendar_extended 
                SET scheduled_at = ?, updated_at = datetime('now')
                WHERE id = ?
            ");
            
            $stmt->execute([$bestTime, $entryId]);
            return $bestTime;
        }
        
        return false;
    }
    
    /**
     * Generate content suggestions based on calendar gaps
     */
    public function generateContentSuggestions($startDate, $endDate, $platforms) {
        $entries = $this->getCalendarEntries($startDate, $endDate, $platforms);
        
        // Analyze posting frequency
        $postingFrequency = [];
        foreach ($platforms as $platform) {
            $postingFrequency[$platform] = 0;
        }
        
        foreach ($entries as $entry) {
            foreach ($entry['platforms'] as $platform) {
                $postingFrequency[$platform]++;
            }
        }
        
        // Calculate date range in days
        $days = (strtotime($endDate) - strtotime($startDate)) / (24 * 60 * 60);
        
        $suggestions = [];
        foreach ($platforms as $platform) {
            $currentFrequency = $postingFrequency[$platform] / $days;
            $recommendedFrequency = $this->getRecommendedPostingFrequency($platform);
            
            if ($currentFrequency < $recommendedFrequency) {
                $gap = ($recommendedFrequency - $currentFrequency) * $days;
                $suggestions[] = [
                    'platform' => $platform,
                    'current_frequency' => round($currentFrequency, 2),
                    'recommended_frequency' => $recommendedFrequency,
                    'suggested_additional_posts' => ceil($gap),
                    'priority' => $gap > 5 ? 'high' : 'medium'
                ];
            }
        }
        
        return $suggestions;
    }
    
    // Private helper methods
    private function createRecurringEntries($baseEntryId, $data) {
        $rule = $data['recurring_rule'];
        $endDate = $rule['end_date'] ?? date('Y-m-d', strtotime('+1 year'));
        $frequency = $rule['frequency']; // daily, weekly, monthly
        $interval = $rule['interval'] ?? 1;
        
        $currentDate = new DateTime($data['scheduled_at']);
        $endDateTime = new DateTime($endDate);
        
        while ($currentDate <= $endDateTime) {
            // Add interval based on frequency
            switch ($frequency) {
                case 'daily':
                    $currentDate->add(new DateInterval("P{$interval}D"));
                    break;
                case 'weekly':
                    $currentDate->add(new DateInterval("P{$interval}W"));
                    break;
                case 'monthly':
                    $currentDate->add(new DateInterval("P{$interval}M"));
                    break;
            }
            
            if ($currentDate <= $endDateTime) {
                $newData = $data;
                $newData['scheduled_at'] = $currentDate->format('Y-m-d H:i:s');
                $newData['calendar_id'] = 'cal_' . uniqid();
                unset($newData['recurring_rule']); // Don't create recursive entries
                
                $this->createCalendarEntry($newData);
            }
        }
    }
    
    private function createPostingJob($entryId) {
        $jobId = 'job_' . uniqid();
        
        $stmt = $this->db->prepare("
            INSERT INTO automation_job_queue 
            (job_id, job_type, payload, scheduled_at)
            VALUES (?, 'post_content', ?, 
                    (SELECT scheduled_at FROM content_calendar_extended WHERE id = ?))
        ");
        
        $payload = json_encode(['content_id' => $entryId]);
        $stmt->execute([$jobId, $payload, $entryId]);
    }
    
    private function calculateScheduledTime($schedule, $index) {
        $baseTime = $schedule['start_time'];
        $interval = $schedule['interval_minutes'] ?? 60;
        
        return date('Y-m-d H:i:s', strtotime($baseTime . " +{$index} * {$interval} minutes"));
    }
    
    private function getCalendarEntry($id) {
        $stmt = $this->db->prepare("SELECT * FROM content_calendar_extended WHERE id = ?");
        $stmt->execute([$id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    private function findBestCrossplatformTime($optimalTimes, $originalTime) {
        // Simplified algorithm - in reality, this would be more sophisticated
        $allTimes = [];
        
        foreach ($optimalTimes as $platform => $times) {
            foreach ($times as $time) {
                $key = $time['day_of_week'] . '_' . $time['hour_of_day'];
                if (!isset($allTimes[$key])) {
                    $allTimes[$key] = 0;
                }
                $allTimes[$key] += $time['avg_engagement'];
            }
        }
        
        if (empty($allTimes)) {
            return null;
        }
        
        arsort($allTimes);
        $bestTimeKey = array_key_first($allTimes);
        list($dayOfWeek, $hour) = explode('_', $bestTimeKey);
        
        // Calculate the next occurrence of this day/hour
        $originalDateTime = new DateTime($originalTime);
        $targetDateTime = clone $originalDateTime;
        
        // Adjust to target day of week and hour
        $currentDayOfWeek = $targetDateTime->format('w');
        $dayDiff = ($dayOfWeek - $currentDayOfWeek + 7) % 7;
        $targetDateTime->modify("+$dayDiff days");
        $targetDateTime->setTime($hour, 0, 0);
        
        return $targetDateTime->format('Y-m-d H:i:s');
    }
    
    private function getRecommendedPostingFrequency($platform) {
        $frequencies = [
            'twitter' => 1.0,    // 1 post per day
            'linkedin' => 0.5,   // 3-4 posts per week
            'facebook' => 0.7,   // 5 posts per week
            'instagram' => 0.8   // 6 posts per week
        ];
        
        return $frequencies[$platform] ?? 0.5;
    }
}