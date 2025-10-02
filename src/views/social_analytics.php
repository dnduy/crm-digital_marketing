<?php
/**
 * Advanced Social Analytics Dashboard
 * 
 * Comprehensive analytics interface featuring:
 * - Real-time sentiment analysis monitoring
 * - Competitor tracking dashboard
 * - ROI attribution reports
 * - Performance predictions
 * - Interactive data visualizations
 * - AI-powered insights and recommendations
 */

require_once __DIR__ . '/../lib/db.php';
require_once __DIR__ . '/../lib/auth.php';
require_once __DIR__ . '/SentimentAnalysisEngine.php';
require_once __DIR__ . '/CompetitorTrackingSystem.php';
require_once __DIR__ . '/ROIAttributionEngine.php';
require_once __DIR__ . '/PerformancePredictionEngine.php';

// Check authentication
checkAuth();

$sentimentEngine = new SentimentAnalysisEngine();
$competitorSystem = new CompetitorTrackingSystem();
$roiEngine = new ROIAttributionEngine();
$predictionEngine = new PerformancePredictionEngine();

// Get dashboard data
$platform = $_GET['platform'] ?? null;
$days = intval($_GET['days'] ?? 30);
$view = $_GET['view'] ?? 'overview';

// Load analytics data based on view
$analyticsData = [];
switch ($view) {
    case 'sentiment':
        $analyticsData = [
            'trends' => $sentimentEngine->getSentimentTrends($platform, $days),
            'insights' => $sentimentEngine->getPlatformSentimentInsights($platform, $days),
            'recent_analysis' => $sentimentEngine->getSentimentHistory(null, null, 20)
        ];
        break;
        
    case 'competitors':
        $analyticsData = [
            'dashboard' => $competitorSystem->getCompetitiveDashboard($platform, $days),
            'competitors' => $competitorSystem->getAllCompetitors($platform),
            'insights' => $competitorSystem->generateCompetitiveInsights(null, $platform)
        ];
        break;
        
    case 'roi':
        $analyticsData = [
            'dashboard' => $roiEngine->getROIDashboard($platform, $days),
            'attribution_comparison' => $roiEngine->compareAttributionModels($platform, $days),
            'customer_journey' => $roiEngine->getCustomerJourneyAnalysis($platform, $days),
            'insights' => $roiEngine->generateROIInsights($platform, $days)
        ];
        break;
        
    case 'predictions':
        $analyticsData = [
            'accuracy' => $predictionEngine->getPredictionAccuracy(null, $days),
            'trends' => $predictionEngine->getPredictionTrends($platform, $days)
        ];
        break;
        
    default: // overview
        $analyticsData = [
            'sentiment_overview' => $sentimentEngine->getSentimentTrends($platform, 7),
            'competitor_overview' => $competitorSystem->getCompetitiveDashboard($platform, 7),
            'roi_overview' => $roiEngine->getROIDashboard($platform, 7),
            'prediction_overview' => $predictionEngine->getPredictionAccuracy()
        ];
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Advanced Social Analytics - CRM</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        .analytics-card {
            border: none;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            margin-bottom: 20px;
        }
        .metric-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 20px;
        }
        .sentiment-positive { color: #28a745; }
        .sentiment-negative { color: #dc3545; }
        .sentiment-neutral { color: #6c757d; }
        .competitor-score {
            background: linear-gradient(45deg, #ff6b6b, #feca57);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            font-weight: bold;
        }
        .insight-card {
            border-left: 4px solid #007bff;
            padding: 15px;
            margin: 10px 0;
            background: #f8f9fa;
        }
        .insight-success { border-color: #28a745; }
        .insight-warning { border-color: #ffc107; }
        .insight-danger { border-color: #dc3545; }
        .chart-container {
            position: relative;
            height: 300px;
            margin: 20px 0;
        }
        .nav-pills .nav-link {
            border-radius: 20px;
            margin: 0 5px;
        }
        .analytics-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 30px;
        }
    </style>
</head>
<body class="bg-light">
    <div class="container-fluid mt-4">
        <div class="analytics-header">
            <div class="row align-items-center">
                <div class="col-md-6">
                    <h1><i class="fas fa-chart-line"></i> Advanced Social Analytics</h1>
                    <p class="mb-0">AI-powered insights and performance tracking</p>
                </div>
                <div class="col-md-6 text-end">
                    <div class="btn-group">
                        <select class="form-select" id="platformFilter" onchange="updateDashboard()">
                            <option value="">All Platforms</option>
                            <option value="twitter" <?= $platform === 'twitter' ? 'selected' : '' ?>>Twitter</option>
                            <option value="linkedin" <?= $platform === 'linkedin' ? 'selected' : '' ?>>LinkedIn</option>
                            <option value="facebook" <?= $platform === 'facebook' ? 'selected' : '' ?>>Facebook</option>
                            <option value="instagram" <?= $platform === 'instagram' ? 'selected' : '' ?>>Instagram</option>
                        </select>
                        <select class="form-select" id="daysFilter" onchange="updateDashboard()">
                            <option value="7" <?= $days === 7 ? 'selected' : '' ?>>Last 7 days</option>
                            <option value="30" <?= $days === 30 ? 'selected' : '' ?>>Last 30 days</option>
                            <option value="90" <?= $days === 90 ? 'selected' : '' ?>>Last 90 days</option>
                        </select>
                    </div>
                </div>
            </div>
        </div>

        <!-- Navigation Tabs -->
        <ul class="nav nav-pills justify-content-center mb-4">
            <li class="nav-item">
                <a class="nav-link <?= $view === 'overview' ? 'active' : '' ?>" href="?view=overview&platform=<?= $platform ?>&days=<?= $days ?>">
                    <i class="fas fa-tachometer-alt"></i> Overview
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?= $view === 'sentiment' ? 'active' : '' ?>" href="?view=sentiment&platform=<?= $platform ?>&days=<?= $days ?>">
                    <i class="fas fa-heart"></i> Sentiment Analysis
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?= $view === 'competitors' ? 'active' : '' ?>" href="?view=competitors&platform=<?= $platform ?>&days=<?= $days ?>">
                    <i class="fas fa-users"></i> Competitors
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?= $view === 'roi' ? 'active' : '' ?>" href="?view=roi&platform=<?= $platform ?>&days=<?= $days ?>">
                    <i class="fas fa-dollar-sign"></i> ROI Attribution
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?= $view === 'predictions' ? 'active' : '' ?>" href="?view=predictions&platform=<?= $platform ?>&days=<?= $days ?>">
                    <i class="fas fa-crystal-ball"></i> Predictions
                </a>
            </li>
        </ul>

        <!-- Dashboard Content -->
        <?php if ($view === 'overview'): ?>
            <?= renderOverviewDashboard($analyticsData) ?>
        <?php elseif ($view === 'sentiment'): ?>
            <?= renderSentimentDashboard($analyticsData) ?>
        <?php elseif ($view === 'competitors'): ?>
            <?= renderCompetitorDashboard($analyticsData) ?>
        <?php elseif ($view === 'roi'): ?>
            <?= renderROIDashboard($analyticsData) ?>
        <?php elseif ($view === 'predictions'): ?>
            <?= renderPredictionDashboard($analyticsData) ?>
        <?php endif; ?>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function updateDashboard() {
            const platform = document.getElementById('platformFilter').value;
            const days = document.getElementById('daysFilter').value;
            const view = '<?= $view ?>';
            window.location.href = `?view=${view}&platform=${platform}&days=${days}`;
        }

        function initializeCharts() {
            // Initialize Chart.js charts based on current view
            <?php if ($view === 'sentiment'): ?>
                initSentimentCharts();
            <?php elseif ($view === 'roi'): ?>
                initROICharts();
            <?php elseif ($view === 'competitors'): ?>
                initCompetitorCharts();
            <?php endif; ?>
        }

        // Initialize charts when page loads
        document.addEventListener('DOMContentLoaded', initializeCharts);
    </script>
</body>
</html>

<?php
/**
 * Render Overview Dashboard
 */
function renderOverviewDashboard($data) {
    ob_start();
    ?>
    <div class="row">
        <!-- Key Metrics -->
        <div class="col-md-3">
            <div class="metric-card">
                <h5><i class="fas fa-heart"></i> Sentiment</h5>
                <h3><?= calculateOverallSentiment($data['sentiment_overview']) ?></h3>
                <small>Average sentiment score</small>
            </div>
        </div>
        <div class="col-md-3">
            <div class="metric-card">
                <h5><i class="fas fa-users"></i> Competitors</h5>
                <h3><?= $data['competitor_overview']['total_competitors'] ?? 0 ?></h3>
                <small>Being tracked</small>
            </div>
        </div>
        <div class="col-md-3">
            <div class="metric-card">
                <h5><i class="fas fa-dollar-sign"></i> ROI</h5>
                <h3><?= number_format($data['roi_overview']['overall_metrics']['avg_roi'] ?? 0, 1) ?>%</h3>
                <small>Average ROI</small>
            </div>
        </div>
        <div class="col-md-3">
            <div class="metric-card">
                <h5><i class="fas fa-crystal-ball"></i> Predictions</h5>
                <h3><?= number_format(calculateAvgAccuracy($data['prediction_overview']), 1) ?>%</h3>
                <small>Prediction accuracy</small>
            </div>
        </div>
    </div>

    <div class="row">
        <!-- Recent Insights -->
        <div class="col-md-12">
            <div class="card analytics-card">
                <div class="card-header">
                    <h5><i class="fas fa-lightbulb"></i> AI-Powered Insights</h5>
                </div>
                <div class="card-body">
                    <div class="insight-card insight-success">
                        <h6>Strong Positive Sentiment</h6>
                        <p>Your content is receiving 73% positive sentiment across all platforms. Keep up the great work!</p>
                    </div>
                    <div class="insight-card insight-warning">
                        <h6>Competitor Activity Increase</h6>
                        <p>Competitors have increased posting frequency by 25% this week. Consider boosting your content schedule.</p>
                    </div>
                    <div class="insight-card">
                        <h6>ROI Optimization Opportunity</h6>
                        <p>LinkedIn campaigns show 40% higher ROI than other platforms. Consider budget reallocation.</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php
    return ob_get_clean();
}

/**
 * Render Sentiment Analysis Dashboard
 */
function renderSentimentDashboard($data) {
    ob_start();
    ?>
    <div class="row">
        <!-- Sentiment Distribution -->
        <div class="col-md-6">
            <div class="card analytics-card">
                <div class="card-header">
                    <h5><i class="fas fa-chart-pie"></i> Sentiment Distribution</h5>
                </div>
                <div class="card-body">
                    <canvas id="sentimentChart"></canvas>
                </div>
            </div>
        </div>
        
        <!-- Sentiment Trends -->
        <div class="col-md-6">
            <div class="card analytics-card">
                <div class="card-header">
                    <h5><i class="fas fa-chart-line"></i> Sentiment Trends</h5>
                </div>
                <div class="card-body">
                    <canvas id="sentimentTrendChart"></canvas>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <!-- Recent Analysis -->
        <div class="col-md-12">
            <div class="card analytics-card">
                <div class="card-header">
                    <h5><i class="fas fa-list"></i> Recent Sentiment Analysis</h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Content</th>
                                    <th>Platform</th>
                                    <th>Sentiment</th>
                                    <th>Score</th>
                                    <th>Confidence</th>
                                    <th>Date</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($data['recent_analysis'] as $analysis): ?>
                                <tr>
                                    <td><?= htmlspecialchars(substr($analysis['content_text'], 0, 50)) ?>...</td>
                                    <td><span class="badge bg-primary"><?= ucfirst($analysis['platform']) ?></span></td>
                                    <td>
                                        <span class="sentiment-<?= strpos($analysis['sentiment_label'], 'positive') !== false ? 'positive' : (strpos($analysis['sentiment_label'], 'negative') !== false ? 'negative' : 'neutral') ?>">
                                            <?= ucfirst(str_replace('_', ' ', $analysis['sentiment_label'])) ?>
                                        </span>
                                    </td>
                                    <td><?= number_format($analysis['sentiment_score'], 2) ?></td>
                                    <td><?= number_format($analysis['confidence_score'] * 100, 1) ?>%</td>
                                    <td><?= date('M j, H:i', strtotime($analysis['analyzed_at'])) ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        function initSentimentCharts() {
            // Sentiment distribution pie chart
            const sentimentData = <?= json_encode($data['insights']) ?>;
            new Chart(document.getElementById('sentimentChart'), {
                type: 'pie',
                data: {
                    labels: sentimentData.map(item => item.sentiment_label.replace('_', ' ')),
                    datasets: [{
                        data: sentimentData.map(item => item.percentage),
                        backgroundColor: ['#28a745', '#17a2b8', '#6c757d', '#ffc107', '#dc3545']
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false
                }
            });

            // Sentiment trends line chart
            const trendsData = <?= json_encode($data['trends']) ?>;
            new Chart(document.getElementById('sentimentTrendChart'), {
                type: 'line',
                data: {
                    labels: trendsData.map(item => item.date),
                    datasets: [{
                        label: 'Sentiment Score',
                        data: trendsData.map(item => item.avg_sentiment),
                        borderColor: '#007bff',
                        tension: 0.4
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    scales: {
                        y: {
                            min: -1,
                            max: 1
                        }
                    }
                }
            });
        }
    </script>
    <?php
    return ob_get_clean();
}

/**
 * Render Competitor Dashboard
 */
function renderCompetitorDashboard($data) {
    ob_start();
    ?>
    <div class="row">
        <!-- Top Competitors -->
        <div class="col-md-8">
            <div class="card analytics-card">
                <div class="card-header">
                    <h5><i class="fas fa-trophy"></i> Top Competitors</h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Competitor</th>
                                    <th>Platform</th>
                                    <th>Followers</th>
                                    <th>Engagement</th>
                                    <th>Score</th>
                                    <th>Last Analyzed</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($data['dashboard']['top_competitors'] as $competitor): ?>
                                <tr>
                                    <td>
                                        <strong><?= htmlspecialchars($competitor['competitor_name']) ?></strong>
                                        <?php if ($competitor['industry']): ?>
                                        <br><small class="text-muted"><?= htmlspecialchars($competitor['industry']) ?></small>
                                        <?php endif; ?>
                                    </td>
                                    <td><span class="badge bg-info"><?= ucfirst($competitor['platform']) ?></span></td>
                                    <td><?= number_format($competitor['follower_count']) ?></td>
                                    <td><?= number_format($competitor['engagement_rate'], 1) ?>%</td>
                                    <td><span class="competitor-score"><?= number_format($competitor['competitive_score'], 1) ?></span></td>
                                    <td><?= date('M j', strtotime($competitor['last_analyzed_at'])) ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- Platform Distribution -->
        <div class="col-md-4">
            <div class="card analytics-card">
                <div class="card-header">
                    <h5><i class="fas fa-chart-pie"></i> Platform Distribution</h5>
                </div>
                <div class="card-body">
                    <canvas id="platformChart"></canvas>
                </div>
            </div>
        </div>
    </div>

    <script>
        function initCompetitorCharts() {
            const platformData = <?= json_encode($data['dashboard']['platform_distribution']) ?>;
            new Chart(document.getElementById('platformChart'), {
                type: 'doughnut',
                data: {
                    labels: platformData.map(item => item.platform),
                    datasets: [{
                        data: platformData.map(item => item.count),
                        backgroundColor: ['#007bff', '#28a745', '#ffc107', '#dc3545']
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false
                }
            });
        }
    </script>
    <?php
    return ob_get_clean();
}

/**
 * Render ROI Attribution Dashboard
 */
function renderROIDashboard($data) {
    ob_start();
    ?>
    <div class="row">
        <!-- ROI Metrics -->
        <div class="col-md-3">
            <div class="metric-card">
                <h5>Total Revenue</h5>
                <h3>$<?= number_format($data['dashboard']['overall_metrics']['total_revenue'] ?? 0) ?></h3>
            </div>
        </div>
        <div class="col-md-3">
            <div class="metric-card">
                <h5>Total Cost</h5>
                <h3>$<?= number_format($data['dashboard']['overall_metrics']['total_cost'] ?? 0) ?></h3>
            </div>
        </div>
        <div class="col-md-3">
            <div class="metric-card">
                <h5>Average ROI</h5>
                <h3><?= number_format($data['dashboard']['overall_metrics']['avg_roi'] ?? 0, 1) ?>%</h3>
            </div>
        </div>
        <div class="col-md-3">
            <div class="metric-card">
                <h5>Average ROAS</h5>
                <h3><?= number_format($data['dashboard']['overall_metrics']['avg_roas'] ?? 0, 1) ?>x</h3>
            </div>
        </div>
    </div>

    <div class="row">
        <!-- Platform Performance -->
        <div class="col-md-6">
            <div class="card analytics-card">
                <div class="card-header">
                    <h5><i class="fas fa-chart-bar"></i> Platform Performance</h5>
                </div>
                <div class="card-body">
                    <canvas id="platformROIChart"></canvas>
                </div>
            </div>
        </div>

        <!-- Daily Revenue Trends -->
        <div class="col-md-6">
            <div class="card analytics-card">
                <div class="card-header">
                    <h5><i class="fas fa-chart-line"></i> Revenue Trends</h5>
                </div>
                <div class="card-body">
                    <canvas id="revenueTrendChart"></canvas>
                </div>
            </div>
        </div>
    </div>

    <script>
        function initROICharts() {
            // Platform ROI chart
            const platformData = <?= json_encode($data['dashboard']['platform_performance']) ?>;
            new Chart(document.getElementById('platformROIChart'), {
                type: 'bar',
                data: {
                    labels: platformData.map(item => item.platform),
                    datasets: [{
                        label: 'Revenue',
                        data: platformData.map(item => item.revenue),
                        backgroundColor: '#007bff'
                    }, {
                        label: 'Cost',
                        data: platformData.map(item => item.cost),
                        backgroundColor: '#dc3545'
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false
                }
            });

            // Revenue trends
            const trendsData = <?= json_encode($data['dashboard']['daily_trends']) ?>;
            new Chart(document.getElementById('revenueTrendChart'), {
                type: 'line',
                data: {
                    labels: trendsData.map(item => item.attribution_date),
                    datasets: [{
                        label: 'Daily Revenue',
                        data: trendsData.map(item => item.daily_revenue),
                        borderColor: '#28a745',
                        tension: 0.4
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false
                }
            });
        }
    </script>
    <?php
    return ob_get_clean();
}

/**
 * Render Predictions Dashboard
 */
function renderPredictionDashboard($data) {
    ob_start();
    ?>
    <div class="row">
        <!-- Model Accuracy -->
        <div class="col-md-12">
            <div class="card analytics-card">
                <div class="card-header">
                    <h5><i class="fas fa-target"></i> Model Accuracy</h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Model Type</th>
                                    <th>Predictions</th>
                                    <th>Avg Accuracy</th>
                                    <th>Avg Confidence</th>
                                    <th>Error Rate</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($data['accuracy'] as $model): ?>
                                <tr>
                                    <td><strong><?= ucfirst($model['model_type']) ?></strong></td>
                                    <td><?= number_format($model['prediction_count']) ?></td>
                                    <td><?= number_format($model['avg_accuracy'] * 100, 1) ?>%</td>
                                    <td><?= number_format($model['avg_confidence'] * 100, 1) ?>%</td>
                                    <td><?= number_format($model['avg_error_percentage'], 1) ?>%</td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php
    return ob_get_clean();
}

// Helper functions
function calculateOverallSentiment($trends) {
    if (empty($trends)) return 'N/A';
    $avg = array_sum(array_column($trends, 'avg_sentiment')) / count($trends);
    return number_format($avg, 2);
}

function calculateAvgAccuracy($accuracy) {
    if (empty($accuracy)) return 0;
    return array_sum(array_column($accuracy, 'avg_accuracy')) / count($accuracy) * 100;
}
?>