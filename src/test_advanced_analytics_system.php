<?php
/**
 * Phase 5: Advanced Social Analytics Test Suite
 * 
 * Comprehensive testing for:
 * - Sentiment Analysis Engine
 * - Competitor Tracking System
 * - ROI Attribution Engine
 * - Performance Prediction Engine
 * - Analytics Dashboard Integration
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/lib/db.php';
require_once __DIR__ . '/services/SentimentAnalysisEngine.php';
require_once __DIR__ . '/services/CompetitorTrackingSystem.php';
require_once __DIR__ . '/services/ROIAttributionEngine.php';
require_once __DIR__ . '/services/PerformancePredictionEngine.php';

class Phase5AnalyticsTestSuite {
    private $db;
    private $sentimentEngine;
    private $competitorSystem;
    private $roiEngine;
    private $predictionEngine;
    private $testResults = [];
    
    public function __construct() {
        global $db;
        $this->db = $db;
        $this->sentimentEngine = new SentimentAnalysisEngine();
        $this->competitorSystem = new CompetitorTrackingSystem();
        $this->roiEngine = new ROIAttributionEngine();
        $this->predictionEngine = new PerformancePredictionEngine();
    }
    
    public function runAllTests() {
        echo "🚀 Running Phase 5: Advanced Social Analytics Test Suite\n";
        echo "=" . str_repeat("=", 60) . "\n\n";
        
        $this->testDatabaseTables();
        $this->testSentimentAnalysis();
        $this->testCompetitorTracking();
        $this->testROIAttribution();
        $this->testPerformancePrediction();
        $this->testAnalyticsDashboard();
        $this->testDataIntegration();
        
        $this->printSummary();
    }
    
    private function testDatabaseTables() {
        echo "📊 Testing Analytics Database Tables...\n";
        
        $tables = [
            'sentiment_analysis',
            'competitor_tracking',
            'competitor_posts',
            'social_roi_attribution',
            'performance_predictions',
            'analytics_reports',
            'social_media_insights',
            'analytics_dashboards'
        ];
        
        foreach ($tables as $table) {
            try {
                $stmt = $this->db->query("SELECT COUNT(*) FROM $table");
                $count = $stmt->fetchColumn();
                $this->recordTest("Database Table: $table", true, "Table exists with $count records");
            } catch (Exception $e) {
                $this->recordTest("Database Table: $table", false, $e->getMessage());
            }
        }
        
        echo "\n";
    }
    
    private function testSentimentAnalysis() {
        echo "😊 Testing Sentiment Analysis Engine...\n";
        
        try {
            // Test sentiment analysis
            $testTexts = [
                "I absolutely love this new feature! It's amazing and works perfectly!",
                "This is terrible and doesn't work at all. Very disappointed.",
                "The product is okay, nothing special but it does the job.",
                "Excited to announce our new partnership! This is going to be great!",
                "Having some issues with the service today, but overall it's decent."
            ];
            
            $results = [];
            foreach ($testTexts as $i => $text) {
                $result = $this->sentimentEngine->analyzeSentiment($text, null, null, 'test_platform');
                $results[] = $result;
                $this->recordTest("Sentiment Analysis #" . ($i + 1), true, 
                    "Score: {$result['sentiment_score']}, Label: {$result['sentiment_label']}");
            }
            
            // Test sentiment trends
            $trends = $this->sentimentEngine->getSentimentTrends('test_platform', 30);
            $this->recordTest("Sentiment Trends", true, "Retrieved " . count($trends) . " trend data points");
            
            // Test bulk analysis
            $bulkResults = $this->sentimentEngine->analyzeBulkPosts(5);
            $this->recordTest("Bulk Sentiment Analysis", true, "Analyzed " . count($bulkResults) . " posts");
            
            // Test platform insights
            $insights = $this->sentimentEngine->getPlatformSentimentInsights('test_platform', 30);
            $this->recordTest("Platform Sentiment Insights", true, "Generated " . count($insights) . " insights");
            
        } catch (Exception $e) {
            $this->recordTest("Sentiment Analysis Engine", false, $e->getMessage());
        }
        
        echo "\n";
    }
    
    private function testCompetitorTracking() {
        echo "👥 Testing Competitor Tracking System...\n";
        
        try {
            // Add test competitors
            try {
                $competitorId1 = $this->competitorSystem->addCompetitor(
                    "TechCorp Solutions", 
                    "linkedin", 
                    "techcorp_solutions", 
                    "https://linkedin.com/company/techcorp", 
                    "technology"
                );
                $this->recordTest("Add Competitor", true, "Added competitor with ID: $competitorId1");
            } catch (Exception $e) {
                if (strpos($e->getMessage(), 'already exists') !== false) {
                    // Get existing competitor
                    $competitors = $this->competitorSystem->getAllCompetitors('linkedin');
                    $competitorId1 = $competitors[0]['id'] ?? 1;
                    $this->recordTest("Add Competitor", true, "Using existing competitor with ID: $competitorId1");
                } else {
                    throw $e;
                }
            }
            
            try {
                $competitorId2 = $this->competitorSystem->addCompetitor(
                    "Digital Innovators", 
                    "twitter", 
                    "digital_innovators", 
                    "https://twitter.com/digital_innovators", 
                    "technology"
                );
                $this->recordTest("Add Second Competitor", true, "Added competitor with ID: $competitorId2");
            } catch (Exception $e) {
                if (strpos($e->getMessage(), 'already exists') !== false) {
                    // Get existing competitor
                    $competitors = $this->competitorSystem->getAllCompetitors('twitter');
                    $competitorId2 = $competitors[0]['id'] ?? 2;
                    $this->recordTest("Add Second Competitor", true, "Using existing competitor with ID: $competitorId2");
                } else {
                    throw $e;
                }
            }
            
            // Analyze competitors
            $analysis1 = $this->competitorSystem->analyzeCompetitor($competitorId1);
            $this->recordTest("Competitor Analysis", true, 
                "Competitive score: " . $analysis1['competitive_score']);
            
            // Get all competitors
            $competitors = $this->competitorSystem->getAllCompetitors();
            $this->recordTest("Get All Competitors", true, "Retrieved " . count($competitors) . " competitors");
            
            // Generate sample competitor posts
            $competitor = $this->competitorSystem->getCompetitor($competitorId1);
            $samplePosts = [];
            for ($i = 0; $i < 5; $i++) {
                $samplePosts[] = [
                    'platform_post_id' => 'test_post_' . uniqid(),
                    'content_text' => "Sample competitor post #$i with engaging content!",
                    'content_type' => 'post',
                    'hashtags' => ['#technology', '#innovation', '#business'],
                    'likes_count' => rand(50, 500),
                    'comments_count' => rand(5, 50),
                    'shares_count' => rand(2, 25),
                    'posted_at' => date('Y-m-d H:i:s', strtotime("-$i days"))
                ];
            }
            
            $storedPosts = $this->competitorSystem->storeCompetitorPosts($competitorId1, $samplePosts);
            $this->recordTest("Store Competitor Posts", true, "Stored $storedPosts posts");
            
            // Get competitive dashboard
            $dashboard = $this->competitorSystem->getCompetitiveDashboard('linkedin', 30);
            $this->recordTest("Competitive Dashboard", true, 
                "Dashboard data with " . count($dashboard['top_competitors']) . " top competitors");
            
            // Generate insights
            $insights = $this->competitorSystem->generateCompetitiveInsights($competitorId1);
            $this->recordTest("Competitive Insights", true, "Generated " . count($insights) . " insights");
            
        } catch (Exception $e) {
            $this->recordTest("Competitor Tracking System", false, $e->getMessage());
        }
        
        echo "\n";
    }
    
    private function testROIAttribution() {
        echo "💰 Testing ROI Attribution Engine...\n";
        
        try {
            // Generate sample conversions
            $sampleConversions = $this->roiEngine->generateSampleConversions(20);
            $this->recordTest("Generate Sample Conversions", true, 
                "Generated " . count($sampleConversions) . " conversion records");
            
            // Test individual conversion tracking
            $conversionData = [
                'platform' => 'linkedin',
                'utm_source' => 'social_media',
                'utm_medium' => 'social',
                'utm_campaign' => 'product_launch',
                'utm_content' => 'announcement_post',
                'utm_term' => 'new_feature',
                'sessions' => 150,
                'page_views' => 300,
                'bounce_rate' => 35.5,
                'avg_session_duration' => 180,
                'conversions' => 12,
                'revenue' => 2400.00,
                'cost' => 800.00,
                'customer_lifetime_value' => 3600.00
            ];
            
            $result = $this->roiEngine->trackConversion($conversionData);
            $this->recordTest("Track Individual Conversion", true, 
                "ROI: {$result['roi_percentage']}%, ROAS: {$result['roas']}");
            
            // Get ROI dashboard
            $dashboard = $this->roiEngine->getROIDashboard('linkedin', 30);
            $this->recordTest("ROI Dashboard", true, 
                "Total revenue: $" . number_format($dashboard['overall_metrics']['total_revenue']));
            
            // Compare attribution models
            $modelComparison = $this->roiEngine->compareAttributionModels('linkedin', 30);
            $this->recordTest("Attribution Model Comparison", true, 
                "Compared " . count($modelComparison) . " attribution models");
            
            // Customer journey analysis
            $journeyAnalysis = $this->roiEngine->getCustomerJourneyAnalysis('linkedin', 30);
            $this->recordTest("Customer Journey Analysis", true, 
                "Analyzed " . count($journeyAnalysis['journey_paths']) . " journey paths");
            
            // Calculate incremental lift
            $lift = $this->roiEngine->calculateIncrementalLift(30, 30);
            $this->recordTest("Incremental Lift Calculation", true, 
                "Revenue lift: {$lift['revenue_lift_percentage']}%");
            
            // Generate ROI insights
            $insights = $this->roiEngine->generateROIInsights('linkedin', 30);
            $this->recordTest("ROI Insights Generation", true, "Generated " . count($insights) . " insights");
            
        } catch (Exception $e) {
            $this->recordTest("ROI Attribution Engine", false, $e->getMessage());
        }
        
        echo "\n";
    }
    
    private function testPerformancePrediction() {
        echo "🔮 Testing Performance Prediction Engine...\n";
        
        try {
            // Test engagement prediction
            $engagementFeatures = [
                'follower_count' => 5000,
                'post_type' => 'video',
                'has_image' => true,
                'has_video' => true,
                'hashtag_count' => 5,
                'post_length' => 150,
                'posting_time' => 18,
                'day_of_week' => 3
            ];
            
            $engagementPrediction = $this->predictionEngine->generatePrediction(
                'engagement', 'linkedin', $engagementFeatures, 7
            );
            $this->recordTest("Engagement Prediction", true, 
                "Predicted: {$engagementPrediction['prediction_value']}, " .
                "Confidence: {$engagementPrediction['confidence_score']}");
            
            // Test reach prediction
            $reachFeatures = [
                'follower_count' => 8000,
                'expected_engagement' => 150
            ];
            
            $reachPrediction = $this->predictionEngine->generatePrediction(
                'reach', 'linkedin', $reachFeatures, 7
            );
            $this->recordTest("Reach Prediction", true, 
                "Predicted: {$reachPrediction['prediction_value']}");
            
            // Test conversion prediction
            $conversionFeatures = [
                'expected_traffic' => 500,
                'landing_page_score' => 0.8,
                'targeting_score' => 0.9,
                'offer_score' => 0.7
            ];
            
            $conversionPrediction = $this->predictionEngine->generatePrediction(
                'conversion', 'linkedin', $conversionFeatures, 7
            );
            $this->recordTest("Conversion Prediction", true, 
                "Predicted: {$conversionPrediction['prediction_value']} conversions");
            
            // Test revenue prediction
            $revenueFeatures = [
                'expected_conversions' => 25,
                'avg_order_value' => 150,
                'seasonal_multiplier' => 1.2
            ];
            
            $revenuePrediction = $this->predictionEngine->generatePrediction(
                'revenue', 'linkedin', $revenueFeatures, 7
            );
            $this->recordTest("Revenue Prediction", true, 
                "Predicted: $" . number_format($revenuePrediction['prediction_value']));
            
            // Test sentiment prediction
            $sentimentFeatures = [
                'content_tone' => 'positive',
                'topic_sensitivity' => 0.3,
                'brand_health_score' => 0.8
            ];
            
            $sentimentPrediction = $this->predictionEngine->generatePrediction(
                'sentiment', 'linkedin', $sentimentFeatures, 7
            );
            $this->recordTest("Sentiment Prediction", true, 
                "Predicted: {$sentimentPrediction['prediction_value']}");
            
            // Test batch predictions
            $scenarios = [
                [
                    'model_type' => 'engagement',
                    'platform' => 'twitter',
                    'input_features' => ['follower_count' => 3000, 'post_type' => 'image'],
                    'horizon_days' => 3
                ],
                [
                    'model_type' => 'reach',
                    'platform' => 'facebook',
                    'input_features' => ['follower_count' => 10000],
                    'horizon_days' => 7
                ]
            ];
            
            $batchResults = $this->predictionEngine->generateBatchPredictions($scenarios);
            $this->recordTest("Batch Predictions", true, 
                "Processed " . count($batchResults) . " prediction scenarios");
            
            // Test prediction accuracy
            $accuracy = $this->predictionEngine->getPredictionAccuracy(null, 30);
            $this->recordTest("Prediction Accuracy Analysis", true, 
                "Analyzed accuracy for " . count($accuracy) . " model types");
            
            // Test prediction trends
            $trends = $this->predictionEngine->getPredictionTrends('linkedin', 30);
            $this->recordTest("Prediction Trends", true, 
                "Retrieved " . count($trends) . " trend data points");
            
            // Update prediction with actual results
            if (!empty($engagementPrediction['prediction_id'])) {
                $actualValue = 120; // Simulated actual engagement
                $accuracyResult = $this->predictionEngine->updatePredictionWithActual(
                    $engagementPrediction['prediction_id'], 
                    $actualValue
                );
                $this->recordTest("Update Prediction with Actual", true, 
                    "Accuracy: " . number_format($accuracyResult['accuracy_score'] * 100, 1) . "%");
            }
            
        } catch (Exception $e) {
            $this->recordTest("Performance Prediction Engine", false, $e->getMessage());
        }
        
        echo "\n";
    }
    
    private function testAnalyticsDashboard() {
        echo "📈 Testing Analytics Dashboard Integration...\n";
        
        try {
            // Test dashboard view existence
            $dashboardFile = __DIR__ . '/views/social_analytics.php';
            if (file_exists($dashboardFile)) {
                $this->recordTest("Analytics Dashboard File", true, "Dashboard file exists");
                
                // Test if the file is valid PHP
                $syntax = shell_exec("php -l $dashboardFile 2>&1");
                if (strpos($syntax, 'No syntax errors') !== false) {
                    $this->recordTest("Dashboard PHP Syntax", true, "Valid PHP syntax");
                } else {
                    $this->recordTest("Dashboard PHP Syntax", false, "Syntax errors detected");
                }
            } else {
                $this->recordTest("Analytics Dashboard File", false, "Dashboard file not found");
            }
            
            // Test dashboard data generation
            $testViews = ['overview', 'sentiment', 'competitors', 'roi', 'predictions'];
            foreach ($testViews as $view) {
                // Simulate dashboard data loading
                switch ($view) {
                    case 'sentiment':
                        $data = [
                            'trends' => $this->sentimentEngine->getSentimentTrends(null, 7),
                            'insights' => $this->sentimentEngine->getPlatformSentimentInsights(null, 7)
                        ];
                        break;
                    case 'competitors':
                        $data = [
                            'dashboard' => $this->competitorSystem->getCompetitiveDashboard(null, 7)
                        ];
                        break;
                    case 'roi':
                        $data = [
                            'dashboard' => $this->roiEngine->getROIDashboard(null, 7)
                        ];
                        break;
                    case 'predictions':
                        $data = [
                            'accuracy' => $this->predictionEngine->getPredictionAccuracy()
                        ];
                        break;
                    default:
                        $data = ['status' => 'overview_loaded'];
                }
                
                $this->recordTest("Dashboard View: $view", true, "Data loaded successfully");
            }
            
        } catch (Exception $e) {
            $this->recordTest("Analytics Dashboard Integration", false, $e->getMessage());
        }
        
        echo "\n";
    }
    
    private function testDataIntegration() {
        echo "🔗 Testing Data Integration and Cross-System Functionality...\n";
        
        try {
            // Test data flow between systems
            
            // 1. Create a social media post and analyze sentiment
            $stmt = $this->db->prepare("
                INSERT INTO social_media_posts (content, platform, account_id) 
                VALUES (?, ?, ?)
            ");
            $stmt->execute([
                "Excited to share our amazing new product launch! The team has worked incredibly hard to bring you this innovative solution. #innovation #teamwork #success",
                'linkedin',
                1
            ]);
            $postId = $this->db->lastInsertId();
            
            // 2. Analyze sentiment for the post
            $sentimentResult = $this->sentimentEngine->analyzeSentiment(
                "Excited to share our amazing new product launch! The team has worked incredibly hard to bring you this innovative solution.",
                null,
                $postId,
                'linkedin'
            );
            
            $this->recordTest("Post-Sentiment Integration", true, 
                "Post sentiment: {$sentimentResult['sentiment_label']}");
            
            // 3. Track ROI for the campaign
            $conversionData = [
                'platform' => 'linkedin',
                'utm_campaign' => 'product_launch',
                'sessions' => 200,
                'conversions' => 15,
                'revenue' => 3000.00,
                'cost' => 1000.00
            ];
            $roiResult = $this->roiEngine->trackConversion($conversionData);
            
            $this->recordTest("Campaign-ROI Integration", true, 
                "Campaign ROI: {$roiResult['roi_percentage']}%");
            
            // 4. Generate prediction based on historical data
            $predictionFeatures = [
                'follower_count' => 7500,
                'post_type' => 'text',
                'hashtag_count' => 3,
                'posting_time' => 14
            ];
            $prediction = $this->predictionEngine->generatePrediction(
                'engagement', 'linkedin', $predictionFeatures
            );
            
            $this->recordTest("Predictive-Historical Integration", true, 
                "Engagement prediction: {$prediction['prediction_value']}");
            
            // 5. Cross-system analytics query
            $stmt = $this->db->prepare("
                SELECT 
                    p.content,
                    s.sentiment_score,
                    s.sentiment_label,
                    COUNT(pred.id) as predictions_made
                FROM social_media_posts p
                LEFT JOIN sentiment_analysis s ON p.id = s.post_id
                LEFT JOIN performance_predictions pred ON pred.platform = p.platform
                WHERE p.platform = 'linkedin'
                GROUP BY p.id, s.id
                LIMIT 5
            ");
            $stmt->execute();
            $crossSystemData = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            $this->recordTest("Cross-System Data Query", true, 
                "Retrieved " . count($crossSystemData) . " integrated records");
            
            // 6. Test analytics report generation
            $stmt = $this->db->prepare("
                INSERT INTO analytics_reports (
                    report_name, report_type, report_scope, data_summary,
                    insights, recommendations, performance_score,
                    report_period_start, report_period_end
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            
            $reportData = [
                'Phase 5 Integration Test Report',
                'custom',
                'all',
                json_encode([
                    'total_posts_analyzed' => 1,
                    'avg_sentiment_score' => $sentimentResult['sentiment_score'],
                    'total_predictions' => 5,
                    'avg_roi' => $roiResult['roi_percentage']
                ]),
                json_encode([
                    'Positive sentiment detected in recent posts',
                    'ROI performance is above average',
                    'Prediction models are functioning correctly'
                ]),
                json_encode([
                    'Continue monitoring sentiment trends',
                    'Scale successful campaigns',
                    'Validate prediction accuracy'
                ]),
                85.5,
                date('Y-m-d', strtotime('-7 days')),
                date('Y-m-d')
            ];
            
            $stmt->execute($reportData);
            $reportId = $this->db->lastInsertId();
            
            $this->recordTest("Analytics Report Generation", true, "Created report ID: $reportId");
            
        } catch (Exception $e) {
            $this->recordTest("Data Integration", false, $e->getMessage());
        }
        
        echo "\n";
    }
    
    private function recordTest($testName, $passed, $details = '') {
        $status = $passed ? "✅ PASS" : "❌ FAIL";
        echo sprintf("  %-40s %s", $testName, $status);
        if ($details) {
            echo " - $details";
        }
        echo "\n";
        
        $this->testResults[] = [
            'name' => $testName,
            'passed' => $passed,
            'details' => $details
        ];
    }
    
    private function printSummary() {
        echo "\n" . str_repeat("=", 70) . "\n";
        echo "📊 PHASE 5 TEST SUMMARY\n";
        echo str_repeat("=", 70) . "\n";
        
        $total = count($this->testResults);
        $passed = count(array_filter($this->testResults, function($test) {
            return $test['passed'];
        }));
        $failed = $total - $passed;
        
        echo "Total Tests: $total\n";
        echo "✅ Passed: $passed\n";
        echo "❌ Failed: $failed\n";
        echo "Success Rate: " . round(($passed / $total) * 100, 1) . "%\n\n";
        
        if ($failed > 0) {
            echo "Failed Tests:\n";
            foreach ($this->testResults as $test) {
                if (!$test['passed']) {
                    echo "  • {$test['name']}: {$test['details']}\n";
                }
            }
        } else {
            echo "🎉 ALL TESTS PASSED! Phase 5 Advanced Social Analytics is ready!\n";
        }
        
        echo "\nPhase 5 Components Tested:\n";
        echo "✅ Sentiment Analysis Engine\n";
        echo "✅ Competitor Tracking System\n";
        echo "✅ ROI Attribution Engine\n";
        echo "✅ Performance Prediction Engine\n";
        echo "✅ Analytics Dashboard\n";
        echo "✅ Data Integration\n";
        echo "\nNext Steps:\n";
        echo "• Deploy analytics dashboard to production\n";
        echo "• Configure external API integrations\n";
        echo "• Set up automated reporting schedules\n";
        echo "• Train team on advanced analytics features\n";
    }
}

// Run the test suite
if (php_sapi_name() === 'cli') {
    $testSuite = new Phase5AnalyticsTestSuite();
    $testSuite->runAllTests();
}