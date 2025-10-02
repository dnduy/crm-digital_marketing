<?php
/**
 * Social Media Automation Dashboard
 * 
 * Advanced automation management interface with:
 * - Content Calendar with visual planning
 * - Automation Rules management
 * - Job Queue monitoring
 * - Performance Analytics
 * - Engagement Automation settings
 */

require_once __DIR__ . '/../lib/db.php';
require_once __DIR__ . '/../integrations/SocialMediaAutomationEngine.php';
require_once __DIR__ . '/../integrations/ContentCalendarManager.php';
require_once __DIR__ . '/../integrations/SocialMediaManager.php';

// Initialize managers
$automationEngine = new SocialMediaAutomationEngine();
$calendarManager = new ContentCalendarManager();
$socialMediaManager = new SocialMediaManager();

// Get current view
$view = $_GET['view'] ?? 'calendar';
$action = $_GET['automation_action'] ?? '';

// Handle automation actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action) {
    handleAutomationAction($action, $_POST);
}

function handleAutomationAction($action, $data) {
    global $automationEngine, $calendarManager;
    
    switch ($action) {
        case 'create_calendar_entry':
            $entryId = $calendarManager->createCalendarEntry($data);
            header("Location: ?action=social_automation&view=calendar&success=created&id=$entryId");
            exit;
            
        case 'create_automation_rule':
            $ruleId = $automationEngine->createAutomationRule($data);
            header("Location: ?action=social_automation&view=rules&success=created&id=$ruleId");
            exit;
            
        case 'schedule_ai_content':
            $content = $automationEngine->generateAutomatedContent(
                $data['prompt'],
                $data['platforms'],
                $data['content_type']
            );
            
            $calendarData = [
                'title' => $data['title'],
                'content_type' => $data['content_type'],
                'content_data' => $content,
                'platforms' => $data['platforms'],
                'scheduled_at' => $data['scheduled_at'],
                'auto_optimize' => $data['auto_optimize'] ?? false,
                'created_by' => 1
            ];
            
            $entryId = $calendarManager->createCalendarEntry($calendarData);
            header("Location: ?action=social_automation&view=calendar&success=ai_scheduled&id=$entryId");
            exit;
    }
}

function renderAutomationDashboard() {
    global $view;
    
    echo '<div class="automation-dashboard">';
    
    // Navigation tabs
    renderAutomationTabs($view);
    
    // Main content area
    echo '<div class="automation-content">';
    
    switch ($view) {
        case 'calendar':
            renderContentCalendar();
            break;
        case 'rules':
            renderAutomationRules();
            break;
        case 'jobs':
            renderJobQueue();
            break;
        case 'analytics':
            renderAutomationAnalytics();
            break;
        case 'engagement':
            renderEngagementAutomation();
            break;
        default:
            renderContentCalendar();
    }
    
    echo '</div>';
    echo '</div>';
}

function renderAutomationTabs($activeView) {
    $tabs = [
        'calendar' => '📅 Content Calendar',
        'rules' => '🤖 Automation Rules',
        'jobs' => '⚙️ Job Queue',
        'analytics' => '📊 Analytics',
        'engagement' => '💬 Engagement'
    ];
    
    echo '<div class="automation-tabs">';
    foreach ($tabs as $tab => $label) {
        $active = $tab === $activeView ? 'active' : '';
        echo "<a href='?action=social_automation&view=$tab' class='tab $active'>$label</a>";
    }
    echo '</div>';
}

function renderContentCalendar() {
    global $calendarManager, $socialMediaManager;
    
    $currentMonth = $_GET['month'] ?? date('Y-m');
    $platforms = $_GET['platforms'] ?? null;
    
    // Get calendar data
    list($year, $month) = explode('-', $currentMonth);
    $calendar = $calendarManager->getMonthlyCalendarView($year, $month, $platforms);
    $connectedAccounts = $socialMediaManager->getConnectedAccounts();
    
    echo '<div class="calendar-section">';
    
    // Calendar controls
    echo '<div class="calendar-controls">';
    echo "<h3>📅 Content Calendar - " . date('F Y', strtotime($currentMonth . '-01')) . "</h3>";
    
    $prevMonth = date('Y-m', strtotime($currentMonth . '-01 -1 month'));
    $nextMonth = date('Y-m', strtotime($currentMonth . '-01 +1 month'));
    
    echo "<div class='calendar-nav'>";
    echo "<a href='?action=social_automation&view=calendar&month=$prevMonth' class='btn'>← Previous</a>";
    echo "<a href='?action=social_automation&view=calendar&month=" . date('Y-m') . "' class='btn'>Today</a>";
    echo "<a href='?action=social_automation&view=calendar&month=$nextMonth' class='btn'>Next →</a>";
    echo "</div>";
    
    echo '<div class="calendar-actions">';
    echo '<button onclick="showCreatePostModal()" class="btn btn-primary">+ Schedule Post</button>';
    echo '<button onclick="showAIContentModal()" class="btn btn-success">🤖 AI Content</button>';
    echo '<button onclick="showBulkScheduleModal()" class="btn btn-info">📋 Bulk Schedule</button>';
    echo '</div>';
    
    echo '</div>';
    
    // Platform filter
    echo '<div class="platform-filter">';
    echo '<label>Filter by Platform:</label>';
    $platformOptions = ['twitter', 'linkedin', 'facebook', 'instagram'];
    foreach ($platformOptions as $platform) {
        $checked = in_array($platform, $platforms ?? []) ? 'checked' : '';
        echo "<label><input type='checkbox' name='platforms[]' value='$platform' $checked> " . ucfirst($platform) . "</label>";
    }
    echo '</div>';
    
    // Calendar grid
    renderCalendarGrid($calendar, $year, $month);
    
    echo '</div>';
    
    // Modals
    renderCreatePostModal($connectedAccounts);
    renderAIContentModal($connectedAccounts);
    renderBulkScheduleModal($connectedAccounts);
}

function renderCalendarGrid($calendar, $year, $month) {
    echo '<div class="calendar-grid">';
    
    // Header
    $days = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];
    echo '<div class="calendar-header">';
    foreach ($days as $day) {
        echo "<div class='day-header'>$day</div>";
    }
    echo '</div>';
    
    // Calendar body
    $daysInMonth = date('t', strtotime("$year-$month-01"));
    $firstDayOfWeek = date('w', strtotime("$year-$month-01"));
    
    echo '<div class="calendar-body">';
    
    // Empty cells for days before month starts
    for ($i = 0; $i < $firstDayOfWeek; $i++) {
        echo '<div class="calendar-day empty"></div>';
    }
    
    // Days of the month
    for ($day = 1; $day <= $daysInMonth; $day++) {
        $date = sprintf('%04d-%02d-%02d', $year, $month, $day);
        $entries = $calendar[$date] ?? [];
        $isToday = $date === date('Y-m-d') ? 'today' : '';
        
        echo "<div class='calendar-day $isToday' data-date='$date'>";
        echo "<div class='day-number'>$day</div>";
        
        if (!empty($entries)) {
            echo '<div class="day-entries">';
            foreach ($entries as $entry) {
                $time = date('H:i', strtotime($entry['scheduled_at']));
                $status = $entry['status'];
                $platforms = implode(',', $entry['platforms']);
                
                echo "<div class='entry entry-$status' title='{$entry['title']}' onclick='showEntryDetails({$entry['id']})'>";
                echo "<span class='entry-time'>$time</span>";
                echo "<span class='entry-title'>" . substr($entry['title'], 0, 20) . "...</span>";
                echo "<span class='entry-platforms'>$platforms</span>";
                echo "</div>";
            }
            echo '</div>';
        }
        
        echo '</div>';
    }
    
    echo '</div>';
    echo '</div>';
}

function renderAutomationRules() {
    global $automationEngine;
    
    echo '<div class="rules-section">';
    echo '<div class="section-header">';
    echo '<h3>🤖 Automation Rules</h3>';
    echo '<button onclick="showCreateRuleModal()" class="btn btn-primary">+ Create Rule</button>';
    echo '</div>';
    
    // Get automation rules
    $stmt = $GLOBALS['db']->query("
        SELECT * FROM social_media_automation_rules 
        ORDER BY priority DESC, created_at DESC
    ");
    $rules = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($rules)) {
        echo '<div class="empty-state">';
        echo '<p>No automation rules created yet.</p>';
        echo '<p>Create your first rule to automate content posting, engagement, and optimization.</p>';
        echo '</div>';
    } else {
        echo '<div class="rules-grid">';
        foreach ($rules as $rule) {
            renderRuleCard($rule);
        }
        echo '</div>';
    }
    
    echo '</div>';
    
    renderCreateRuleModal();
}

function renderRuleCard($rule) {
    $platforms = implode(', ', json_decode($rule['platforms'], true));
    $isActive = $rule['is_active'] ? 'active' : 'inactive';
    $lastExecuted = $rule['last_executed_at'] ? date('M j, H:i', strtotime($rule['last_executed_at'])) : 'Never';
    
    echo "<div class='rule-card rule-$isActive'>";
    echo "<div class='rule-header'>";
    echo "<h4>{$rule['name']}</h4>";
    echo "<span class='rule-type'>{$rule['rule_type']}</span>";
    echo "</div>";
    
    echo "<div class='rule-body'>";
    echo "<p>{$rule['description']}</p>";
    echo "<div class='rule-meta'>";
    echo "<span><strong>Platforms:</strong> $platforms</span>";
    echo "<span><strong>Priority:</strong> {$rule['priority']}</span>";
    echo "<span><strong>Last Executed:</strong> $lastExecuted</span>";
    echo "<span><strong>Executions:</strong> {$rule['execution_count']}</span>";
    echo "</div>";
    echo "</div>";
    
    echo "<div class='rule-actions'>";
    echo "<button onclick='editRule({$rule['id']})' class='btn btn-sm'>Edit</button>";
    echo "<button onclick='toggleRule({$rule['id']}, {$rule['is_active']})' class='btn btn-sm'>";
    echo $rule['is_active'] ? 'Disable' : 'Enable';
    echo "</button>";
    echo "<button onclick='deleteRule({$rule['id']})' class='btn btn-sm btn-danger'>Delete</button>";
    echo "</div>";
    
    echo "</div>";
}

function renderJobQueue() {
    echo '<div class="jobs-section">';
    echo '<div class="section-header">';
    echo '<h3>⚙️ Job Queue</h3>';
    echo '<button onclick="processJobQueue()" class="btn btn-primary">▶️ Process Queue</button>';
    echo '</div>';
    
    // Get job queue status
    $stmt = $GLOBALS['db']->query("
        SELECT status, COUNT(*) as count 
        FROM automation_job_queue 
        GROUP BY status
    ");
    $statusCounts = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
    
    // Job status overview
    echo '<div class="job-status-overview">';
    foreach (['pending', 'processing', 'completed', 'failed'] as $status) {
        $count = $statusCounts[$status] ?? 0;
        echo "<div class='status-card status-$status'>";
        echo "<h4>$count</h4>";
        echo "<p>" . ucfirst($status) . "</p>";
        echo "</div>";
    }
    echo '</div>';
    
    // Recent jobs
    $stmt = $GLOBALS['db']->query("
        SELECT * FROM automation_job_queue 
        ORDER BY created_at DESC 
        LIMIT 20
    ");
    $recentJobs = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo '<div class="jobs-table">';
    echo '<h4>Recent Jobs</h4>';
    echo '<table>';
    echo '<thead>';
    echo '<tr><th>Job ID</th><th>Type</th><th>Status</th><th>Scheduled</th><th>Attempts</th><th>Actions</th></tr>';
    echo '</thead>';
    echo '<tbody>';
    
    foreach ($recentJobs as $job) {
        $scheduledAt = date('M j, H:i', strtotime($job['scheduled_at']));
        echo "<tr class='job-row job-{$job['status']}'>";
        echo "<td>{$job['job_id']}</td>";
        echo "<td>{$job['job_type']}</td>";
        echo "<td><span class='status-badge status-{$job['status']}'>{$job['status']}</span></td>";
        echo "<td>$scheduledAt</td>";
        echo "<td>{$job['attempts']}/{$job['max_attempts']}</td>";
        echo "<td>";
        if ($job['status'] === 'failed') {
            echo "<button onclick='retryJob(\"{$job['job_id']}\")' class='btn btn-sm'>Retry</button>";
        }
        echo "<button onclick='viewJobDetails(\"{$job['job_id']}\")' class='btn btn-sm'>Details</button>";
        echo "</td>";
        echo "</tr>";
    }
    
    echo '</tbody>';
    echo '</table>';
    echo '</div>';
    
    echo '</div>';
}

function renderAutomationAnalytics() {
    echo '<div class="analytics-section">';
    echo '<h3>📊 Automation Analytics</h3>';
    
    // Performance metrics
    $stmt = $GLOBALS['db']->query("
        SELECT 
            DATE(created_at) as date,
            COUNT(*) as total_jobs,
            SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed_jobs,
            SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) as failed_jobs
        FROM automation_job_queue 
        WHERE created_at >= date('now', '-30 days')
        GROUP BY DATE(created_at)
        ORDER BY date DESC
    ");
    $dailyStats = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo '<div class="analytics-grid">';
    
    // Success rate chart
    echo '<div class="analytics-card">';
    echo '<h4>Job Success Rate (Last 30 Days)</h4>';
    echo '<div class="chart-container">';
    renderSuccessRateChart($dailyStats);
    echo '</div>';
    echo '</div>';
    
    // Rule execution stats
    echo '<div class="analytics-card">';
    echo '<h4>Top Performing Rules</h4>';
    $stmt = $GLOBALS['db']->query("
        SELECT name, execution_count, 
               CASE WHEN execution_count > 0 THEN 
                   (SELECT COUNT(*) FROM automation_logs WHERE rule_id = r.id AND status = 'success') * 100.0 / execution_count 
               ELSE 0 END as success_rate
        FROM social_media_automation_rules r
        WHERE execution_count > 0
        ORDER BY execution_count DESC
        LIMIT 10
    ");
    $ruleStats = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo '<table>';
    echo '<thead><tr><th>Rule Name</th><th>Executions</th><th>Success Rate</th></tr></thead>';
    echo '<tbody>';
    foreach ($ruleStats as $stat) {
        echo "<tr>";
        echo "<td>{$stat['name']}</td>";
        echo "<td>{$stat['execution_count']}</td>";
        echo "<td>" . round($stat['success_rate'], 1) . "%</td>";
        echo "</tr>";
    }
    echo '</tbody>';
    echo '</table>';
    echo '</div>';
    
    echo '</div>';
    echo '</div>';
}

function renderSuccessRateChart($data) {
    // Simple text-based chart for now
    echo '<div class="simple-chart">';
    foreach (array_slice($data, 0, 7) as $day) {
        $successRate = $day['total_jobs'] > 0 ? ($day['completed_jobs'] / $day['total_jobs']) * 100 : 0;
        $date = date('M j', strtotime($day['date']));
        
        echo "<div class='chart-bar'>";
        echo "<div class='bar-label'>$date</div>";
        echo "<div class='bar-container'>";
        echo "<div class='bar' style='width: {$successRate}%'></div>";
        echo "</div>";
        echo "<div class='bar-value'>" . round($successRate) . "%</div>";
        echo "</div>";
    }
    echo '</div>';
}

// Modal rendering functions
function renderCreatePostModal($accounts) {
    echo '<div id="createPostModal" class="modal">';
    echo '<div class="modal-content">';
    echo '<span class="close">&times;</span>';
    echo '<h3>📝 Schedule New Post</h3>';
    
    echo '<form method="POST" action="?action=social_automation&automation_action=create_calendar_entry">';
    
    echo '<div class="form-group">';
    echo '<label>Title:</label>';
    echo '<input type="text" name="title" required>';
    echo '</div>';
    
    echo '<div class="form-group">';
    echo '<label>Content Type:</label>';
    echo '<select name="content_type" required>';
    echo '<option value="post">Text Post</option>';
    echo '<option value="image">Image Post</option>';
    echo '<option value="video">Video Post</option>';
    echo '<option value="article">Article</option>';
    echo '</select>';
    echo '</div>';
    
    echo '<div class="form-group">';
    echo '<label>Platforms:</label>';
    foreach (['twitter', 'linkedin', 'facebook'] as $platform) {
        echo "<label><input type='checkbox' name='platforms[]' value='$platform'> " . ucfirst($platform) . "</label>";
    }
    echo '</div>';
    
    echo '<div class="form-group">';
    echo '<label>Scheduled Time:</label>';
    echo '<input type="datetime-local" name="scheduled_at" required>';
    echo '</div>';
    
    echo '<div class="form-group">';
    echo '<label><input type="checkbox" name="auto_optimize" value="1"> Auto-optimize posting time</label>';
    echo '</div>';
    
    echo '<div class="form-actions">';
    echo '<button type="submit" class="btn btn-primary">Schedule Post</button>';
    echo '<button type="button" class="btn" onclick="closeModal()">Cancel</button>';
    echo '</div>';
    
    echo '</form>';
    echo '</div>';
    echo '</div>';
}

function renderAIContentModal($accounts) {
    echo '<div id="aiContentModal" class="modal">';
    echo '<div class="modal-content">';
    echo '<span class="close">&times;</span>';
    echo '<h3>🤖 AI-Generated Content</h3>';
    
    echo '<form method="POST" action="?action=social_automation&automation_action=schedule_ai_content">';
    
    echo '<div class="form-group">';
    echo '<label>Content Prompt:</label>';
    echo '<textarea name="prompt" placeholder="Describe what you want to post about..." required></textarea>';
    echo '</div>';
    
    echo '<div class="form-group">';
    echo '<label>Title:</label>';
    echo '<input type="text" name="title" required>';
    echo '</div>';
    
    echo '<div class="form-group">';
    echo '<label>Content Type:</label>';
    echo '<select name="content_type" required>';
    echo '<option value="post">Text Post</option>';
    echo '<option value="image">Image Post</option>';
    echo '<option value="article">Article</option>';
    echo '</select>';
    echo '</div>';
    
    echo '<div class="form-group">';
    echo '<label>Platforms:</label>';
    foreach (['twitter', 'linkedin', 'facebook'] as $platform) {
        echo "<label><input type='checkbox' name='platforms[]' value='$platform'> " . ucfirst($platform) . "</label>";
    }
    echo '</div>';
    
    echo '<div class="form-group">';
    echo '<label>Scheduled Time:</label>';
    echo '<input type="datetime-local" name="scheduled_at" required>';
    echo '</div>';
    
    echo '<div class="form-group">';
    echo '<label><input type="checkbox" name="auto_optimize" value="1"> Auto-optimize posting time</label>';
    echo '</div>';
    
    echo '<div class="form-actions">';
    echo '<button type="submit" class="btn btn-success">Generate & Schedule</button>';
    echo '<button type="button" class="btn" onclick="closeModal()">Cancel</button>';
    echo '</div>';
    
    echo '</form>';
    echo '</div>';
    echo '</div>';
}

function renderBulkScheduleModal($accounts) {
    echo '<div id="bulkScheduleModal" class="modal">';
    echo '<div class="modal-content">';
    echo '<span class="close">&times;</span>';
    echo '<h3>📋 Bulk Schedule Posts</h3>';
    echo '<p>Upload CSV or enter multiple posts to schedule automatically</p>';
    echo '</div>';
    echo '</div>';
}

function renderCreateRuleModal() {
    echo '<div id="createRuleModal" class="modal">';
    echo '<div class="modal-content">';
    echo '<span class="close">&times;</span>';
    echo '<h3>🤖 Create Automation Rule</h3>';
    
    echo '<form method="POST" action="?action=social_automation&automation_action=create_automation_rule">';
    
    echo '<div class="form-group">';
    echo '<label>Rule Name:</label>';
    echo '<input type="text" name="name" required>';
    echo '</div>';
    
    echo '<div class="form-group">';
    echo '<label>Description:</label>';
    echo '<textarea name="description"></textarea>';
    echo '</div>';
    
    echo '<div class="form-group">';
    echo '<label>Rule Type:</label>';
    echo '<select name="rule_type" required>';
    echo '<option value="scheduled_post">Scheduled Post</option>';
    echo '<option value="auto_reply">Auto Reply</option>';
    echo '<option value="content_curation">Content Curation</option>';
    echo '<option value="engagement_boost">Engagement Boost</option>';
    echo '<option value="optimal_timing">Optimal Timing</option>';
    echo '</select>';
    echo '</div>';
    
    echo '<div class="form-group">';
    echo '<label>Platforms:</label>';
    foreach (['twitter', 'linkedin', 'facebook'] as $platform) {
        echo "<label><input type='checkbox' name='platforms[]' value='$platform'> " . ucfirst($platform) . "</label>";
    }
    echo '</div>';
    
    echo '<div class="form-actions">';
    echo '<button type="submit" class="btn btn-primary">Create Rule</button>';
    echo '<button type="button" class="btn" onclick="closeModal()">Cancel</button>';
    echo '</div>';
    
    echo '</form>';
    echo '</div>';
    echo '</div>';
}

// CSS Styles
echo '<style>
.automation-dashboard {
    max-width: 1200px;
    margin: 0 auto;
    padding: 20px;
}

.automation-tabs {
    display: flex;
    border-bottom: 2px solid #ddd;
    margin-bottom: 20px;
}

.tab {
    padding: 10px 20px;
    text-decoration: none;
    color: #666;
    border-bottom: 2px solid transparent;
}

.tab.active {
    color: #007cba;
    border-bottom-color: #007cba;
}

.calendar-grid {
    display: grid;
    gap: 1px;
    background: #ddd;
    border: 1px solid #ddd;
}

.calendar-header {
    display: grid;
    grid-template-columns: repeat(7, 1fr);
    background: #f5f5f5;
}

.day-header {
    padding: 10px;
    text-align: center;
    font-weight: bold;
    background: white;
}

.calendar-body {
    display: grid;
    grid-template-columns: repeat(7, 1fr);
}

.calendar-day {
    min-height: 120px;
    background: white;
    padding: 5px;
    position: relative;
}

.calendar-day.today {
    background: #e8f4fd;
}

.day-number {
    font-weight: bold;
    margin-bottom: 5px;
}

.entry {
    font-size: 11px;
    margin: 2px 0;
    padding: 2px 4px;
    border-radius: 3px;
    cursor: pointer;
}

.entry-scheduled { background: #fff3cd; }
.entry-published { background: #d4edda; }
.entry-failed { background: #f8d7da; }

.modal {
    display: none;
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0,0,0,0.5);
    z-index: 1000;
}

.modal-content {
    background: white;
    margin: 5% auto;
    padding: 20px;
    width: 80%;
    max-width: 600px;
    border-radius: 5px;
}

.form-group {
    margin-bottom: 15px;
}

.form-group label {
    display: block;
    margin-bottom: 5px;
    font-weight: bold;
}

.form-group input, .form-group textarea, .form-group select {
    width: 100%;
    padding: 8px;
    border: 1px solid #ddd;
    border-radius: 3px;
}

.btn {
    padding: 8px 16px;
    background: #007cba;
    color: white;
    border: none;
    border-radius: 3px;
    cursor: pointer;
    text-decoration: none;
    display: inline-block;
}

.btn:hover {
    background: #005a87;
}
</style>';

// JavaScript for modal handling
echo '<script>
function showCreatePostModal() {
    document.getElementById("createPostModal").style.display = "block";
}

function showAIContentModal() {
    document.getElementById("aiContentModal").style.display = "block";
}

function showCreateRuleModal() {
    document.getElementById("createRuleModal").style.display = "block";
}

function closeModal() {
    document.querySelectorAll(".modal").forEach(modal => modal.style.display = "none");
}

// Close modal when clicking outside
window.onclick = function(event) {
    if (event.target.classList.contains("modal")) {
        event.target.style.display = "none";
    }
}
</script>';

// Render the main dashboard
renderAutomationDashboard();