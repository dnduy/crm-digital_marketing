<?php

namespace Services;

use Core\EventDispatcher;
use Core\Logger;

/**
 * Queue Service
 * Manages background job processing for automation
 */
class QueueService
{
    private EventDispatcher $events;
    private Logger $logger;
    private array $config;
    private \PDO $db;
    
    public function __construct(
        EventDispatcher $events,
        Logger $logger,
        \PDO $db,
        array $config = []
    ) {
        $this->events = $events;
        $this->logger = $logger;
        $this->db = $db;
        $this->config = $config;
        $this->initializeTables();
    }

    /**
     * Add job to queue
     */
    public function push(string $jobClass, array $data = [], array $options = []): int
    {
        $jobData = [
            'job_class' => $jobClass,
            'payload' => json_encode($data),
            'priority' => $options['priority'] ?? 0,
            'delay' => $options['delay'] ?? 0,
            'max_attempts' => $options['max_attempts'] ?? 3,
            'queue' => $options['queue'] ?? 'default',
            'status' => 'pending',
            'created_at' => date('Y-m-d H:i:s'),
            'available_at' => date('Y-m-d H:i:s', time() + ($options['delay'] ?? 0))
        ];

        $sql = "INSERT INTO job_queue (job_class, payload, priority, delay_seconds, max_attempts, queue_name, status, created_at, available_at) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            $jobData['job_class'],
            $jobData['payload'],
            $jobData['priority'],
            $jobData['delay'],
            $jobData['max_attempts'],
            $jobData['queue'],
            $jobData['status'],
            $jobData['created_at'],
            $jobData['available_at']
        ]);

        $jobId = $this->db->lastInsertId();

        $this->logger->info('Job queued', [
            'job_id' => $jobId,
            'job_class' => $jobClass,
            'queue' => $jobData['queue']
        ]);

        $this->events->dispatch('queue.job_queued', [
            'job_id' => $jobId,
            'job_class' => $jobClass,
            'data' => $data
        ]);

        return $jobId;
    }

    /**
     * Process jobs in queue
     */
    public function work(string $queue = 'default', int $maxJobs = 0): void
    {
        $this->logger->info("Starting queue worker for queue: {$queue}");
        
        $jobsProcessed = 0;
        
        while (true) {
            $job = $this->getNextJob($queue);
            
            if (!$job) {
                if ($maxJobs > 0 && $jobsProcessed >= $maxJobs) {
                    break;
                }
                sleep(5); // Wait before checking again
                continue;
            }

            try {
                $this->processJob($job);
                $jobsProcessed++;
                
                if ($maxJobs > 0 && $jobsProcessed >= $maxJobs) {
                    break;
                }
                
            } catch (\Exception $e) {
                $this->logger->error('Queue worker error', [
                    'error' => $e->getMessage(),
                    'job_id' => $job['id']
                ]);
            }
        }

        $this->logger->info("Queue worker stopped. Processed {$jobsProcessed} jobs.");
    }

    /**
     * Process single job
     */
    public function processJob(array $job): void
    {
        $jobId = $job['id'];
        
        try {
            // Mark job as processing
            $this->updateJobStatus($jobId, 'processing', [
                'started_at' => date('Y-m-d H:i:s')
            ]);

            // Load job class
            $jobClass = $job['job_class'];
            if (!class_exists($jobClass)) {
                throw new \Exception("Job class not found: {$jobClass}");
            }

            // Create job instance
            $jobInstance = new $jobClass();
            if (!method_exists($jobInstance, 'handle')) {
                throw new \Exception("Job class must have handle() method: {$jobClass}");
            }

            // Execute job
            $payload = json_decode($job['payload'], true) ?: [];
            $result = $jobInstance->handle($payload);

            // Mark as completed
            $this->updateJobStatus($jobId, 'completed', [
                'completed_at' => date('Y-m-d H:i:s'),
                'result' => json_encode($result)
            ]);

            $this->logger->info('Job completed successfully', [
                'job_id' => $jobId,
                'job_class' => $jobClass
            ]);

            $this->events->dispatch('queue.job_completed', [
                'job_id' => $jobId,
                'job_class' => $jobClass,
                'result' => $result
            ]);

        } catch (\Exception $e) {
            $this->handleJobFailure($job, $e);
        }
    }

    /**
     * Handle job failure
     */
    private function handleJobFailure(array $job, \Exception $e): void
    {
        $jobId = $job['id'];
        $attempts = $job['attempts'] + 1;
        
        $this->logger->error('Job failed', [
            'job_id' => $jobId,
            'job_class' => $job['job_class'],
            'attempt' => $attempts,
            'error' => $e->getMessage()
        ]);

        if ($attempts >= $job['max_attempts']) {
            // Mark as failed permanently
            $this->updateJobStatus($jobId, 'failed', [
                'failed_at' => date('Y-m-d H:i:s'),
                'error_message' => $e->getMessage(),
                'attempts' => $attempts
            ]);

            $this->events->dispatch('queue.job_failed', [
                'job_id' => $jobId,
                'job_class' => $job['job_class'],
                'error' => $e->getMessage()
            ]);
        } else {
            // Retry with exponential backoff
            $delay = pow(2, $attempts) * 60; // 2^attempts minutes
            $retryAt = date('Y-m-d H:i:s', time() + $delay);
            
            $this->updateJobStatus($jobId, 'pending', [
                'available_at' => $retryAt,
                'attempts' => $attempts,
                'last_error' => $e->getMessage()
            ]);

            $this->logger->info('Job scheduled for retry', [
                'job_id' => $jobId,
                'retry_at' => $retryAt,
                'attempt' => $attempts
            ]);
        }
    }

    /**
     * Get next job from queue
     */
    private function getNextJob(string $queue): ?array
    {
        $sql = "SELECT * FROM job_queue 
                WHERE queue_name = ? 
                AND status = 'pending' 
                AND available_at <= NOW() 
                ORDER BY priority DESC, created_at ASC 
                LIMIT 1 
                FOR UPDATE";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$queue]);
        
        return $stmt->fetch(\PDO::FETCH_ASSOC) ?: null;
    }

    /**
     * Update job status
     */
    private function updateJobStatus(int $jobId, string $status, array $data = []): void
    {
        $updates = ['status = ?'];
        $params = [$status];
        
        foreach ($data as $key => $value) {
            $updates[] = "{$key} = ?";
            $params[] = $value;
        }
        
        $params[] = $jobId;
        
        $sql = "UPDATE job_queue SET " . implode(', ', $updates) . " WHERE id = ?";
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
    }

    /**
     * Get queue statistics
     */
    public function getStats(string $queue = null): array
    {
        $whereClause = $queue ? "WHERE queue_name = ?" : "";
        $params = $queue ? [$queue] : [];
        
        $sql = "SELECT 
                    status,
                    COUNT(*) as count
                FROM job_queue 
                {$whereClause}
                GROUP BY status";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        
        $stats = [];
        while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
            $stats[$row['status']] = (int)$row['count'];
        }
        
        return $stats;
    }

    /**
     * Clear failed jobs
     */
    public function clearFailedJobs(int $olderThanDays = 7): int
    {
        $sql = "DELETE FROM job_queue 
                WHERE status = 'failed' 
                AND failed_at < DATE_SUB(NOW(), INTERVAL ? DAY)";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$olderThanDays]);
        
        return $stmt->rowCount();
    }

    /**
     * Retry failed jobs
     */
    public function retryFailedJobs(array $jobIds = []): int
    {
        $whereClause = empty($jobIds) ? 
            "WHERE status = 'failed'" : 
            "WHERE status = 'failed' AND id IN (" . implode(',', array_map('intval', $jobIds)) . ")";
        
        $sql = "UPDATE job_queue 
                SET status = 'pending', 
                    available_at = NOW(),
                    attempts = 0,
                    last_error = NULL,
                    failed_at = NULL
                {$whereClause}";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute();
        
        return $stmt->rowCount();
    }

    /**
     * Initialize database tables
     */
    private function initializeTables(): void
    {
        $sql = "CREATE TABLE IF NOT EXISTS job_queue (
            id INT AUTO_INCREMENT PRIMARY KEY,
            job_class VARCHAR(255) NOT NULL,
            payload TEXT,
            priority INT DEFAULT 0,
            delay_seconds INT DEFAULT 0,
            max_attempts INT DEFAULT 3,
            attempts INT DEFAULT 0,
            queue_name VARCHAR(100) DEFAULT 'default',
            status ENUM('pending', 'processing', 'completed', 'failed') DEFAULT 'pending',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            available_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            started_at TIMESTAMP NULL,
            completed_at TIMESTAMP NULL,
            failed_at TIMESTAMP NULL,
            last_error TEXT,
            result TEXT,
            INDEX idx_queue_status (queue_name, status),
            INDEX idx_available_at (available_at),
            INDEX idx_priority (priority)
        )";
        
        $this->db->exec($sql);
    }

    /**
     * Schedule recurring job
     */
    public function schedule(string $jobClass, string $schedule, array $data = []): void
    {
        // This would typically use a cron-like scheduler
        // For now, we'll just add to a schedules table
        $sql = "INSERT INTO job_schedules (job_class, schedule, payload, created_at) 
                VALUES (?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE 
                schedule = VALUES(schedule),
                payload = VALUES(payload),
                updated_at = NOW()";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            $jobClass,
            $schedule,
            json_encode($data),
            date('Y-m-d H:i:s')
        ]);

        // Initialize schedules table if needed
        $this->db->exec("CREATE TABLE IF NOT EXISTS job_schedules (
            id INT AUTO_INCREMENT PRIMARY KEY,
            job_class VARCHAR(255) NOT NULL UNIQUE,
            schedule VARCHAR(100) NOT NULL,
            payload TEXT,
            last_run TIMESTAMP NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        )");
    }
}