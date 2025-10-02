<?php
/**
 * ROI Attribution and Revenue Tracking Engine
 * 
 * Tracks and attributes revenue and conversions to social media activities including:
 * - Multi-touch attribution modeling
 * - UTM parameter tracking
 * - Customer journey analysis
 * - Revenue attribution
 * - ROI and ROAS calculation
 * - Customer lifetime value tracking
 */

require_once __DIR__ . '/../lib/db.php';

class ROIAttributionEngine {
    private $db;
    private $attributionModels;
    
    public function __construct() {
        global $db;
        $this->db = $db;
        $this->initializeAttributionModels();
    }
    
    /**
     * Track a conversion event with attribution
     */
    public function trackConversion($conversionData) {
        // Extract required data
        $platform = $conversionData['platform'] ?? 'unknown';
        $utmSource = $conversionData['utm_source'] ?? null;
        $utmMedium = $conversionData['utm_medium'] ?? null;
        $utmCampaign = $conversionData['utm_campaign'] ?? null;
        $utmContent = $conversionData['utm_content'] ?? null;
        $utmTerm = $conversionData['utm_term'] ?? null;
        
        $revenue = $conversionData['revenue'] ?? 0;
        $cost = $conversionData['cost'] ?? 0;
        $sessions = $conversionData['sessions'] ?? 1;
        $pageViews = $conversionData['page_views'] ?? 1;
        $bounceRate = $conversionData['bounce_rate'] ?? 0;
        $sessionDuration = $conversionData['avg_session_duration'] ?? 0;
        $conversions = $conversionData['conversions'] ?? 1;
        $clv = $conversionData['customer_lifetime_value'] ?? $revenue;
        
        // Calculate metrics
        $conversionRate = $sessions > 0 ? round(($conversions / $sessions) * 100, 2) : 0;
        $roiPercentage = $cost > 0 ? round((($revenue - $cost) / $cost) * 100, 2) : 0;
        $roas = $cost > 0 ? round($revenue / $cost, 2) : 0;
        
        // Store attribution data for each model
        foreach ($this->attributionModels as $model) {
            $this->storeAttribution([
                'platform' => $platform,
                'attribution_model' => $model,
                'utm_source' => $utmSource,
                'utm_medium' => $utmMedium,
                'utm_campaign' => $utmCampaign,
                'utm_content' => $utmContent,
                'utm_term' => $utmTerm,
                'sessions' => $sessions,
                'page_views' => $pageViews,
                'bounce_rate' => $bounceRate,
                'avg_session_duration' => $sessionDuration,
                'conversions' => $conversions,
                'conversion_rate' => $conversionRate,
                'revenue' => $revenue,
                'cost' => $cost,
                'roi_percentage' => $roiPercentage,
                'roas' => $roas,
                'customer_lifetime_value' => $clv,
                'attribution_date' => date('Y-m-d')
            ]);
        }
        
        return [
            'conversion_rate' => $conversionRate,
            'roi_percentage' => $roiPercentage,
            'roas' => $roas,
            'revenue' => $revenue,
            'cost' => $cost
        ];
    }
    
    /**
     * Store attribution data
     */
    private function storeAttribution($data) {
        $stmt = $this->db->prepare("
            INSERT INTO social_roi_attribution (
                platform, attribution_model, utm_source, utm_medium, utm_campaign,
                utm_content, utm_term, sessions, page_views, bounce_rate,
                avg_session_duration, conversions, conversion_rate, revenue,
                cost, roi_percentage, roas, customer_lifetime_value, attribution_date
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        
        $stmt->execute([
            $data['platform'],
            $data['attribution_model'],
            $data['utm_source'],
            $data['utm_medium'],
            $data['utm_campaign'],
            $data['utm_content'],
            $data['utm_term'],
            $data['sessions'],
            $data['page_views'],
            $data['bounce_rate'],
            $data['avg_session_duration'],
            $data['conversions'],
            $data['conversion_rate'],
            $data['revenue'],
            $data['cost'],
            $data['roi_percentage'],
            $data['roas'],
            $data['customer_lifetime_value'],
            $data['attribution_date']
        ]);
        
        return $this->db->lastInsertId();
    }
    
    /**
     * Generate sample conversion data for demonstration
     */
    public function generateSampleConversions($count = 50) {
        $platforms = ['twitter', 'linkedin', 'facebook', 'instagram'];
        $campaigns = ['brand_awareness', 'lead_generation', 'product_launch', 'retargeting', 'content_promotion'];
        $sources = ['social_media', 'paid_social', 'organic_social'];
        $mediums = ['social', 'cpc', 'cpm', 'organic'];
        
        $results = [];
        
        for ($i = 0; $i < $count; $i++) {
            $platform = $platforms[array_rand($platforms)];
            $campaign = $campaigns[array_rand($campaigns)];
            $source = $sources[array_rand($sources)];
            $medium = $mediums[array_rand($mediums)];
            
            $sessions = rand(1, 100);
            $pageViews = $sessions * rand(1, 5);
            $conversionCount = rand(0, intval($sessions * 0.1)); // 0-10% conversion rate
            $revenue = $conversionCount * rand(50, 500); // $50-500 per conversion
            $cost = $revenue * rand(20, 80) / 100; // 20-80% of revenue as cost
            
            $conversionData = [
                'platform' => $platform,
                'utm_source' => $source,
                'utm_medium' => $medium,
                'utm_campaign' => $campaign,
                'utm_content' => 'test_content',
                'utm_term' => 'test_term',
                'sessions' => $sessions,
                'page_views' => $pageViews,
                'bounce_rate' => round(rand(20, 80), 1),
                'avg_session_duration' => rand(30, 300),
                'conversions' => $conversionCount,
                'revenue' => $revenue,
                'cost' => $cost,
                'customer_lifetime_value' => $revenue * rand(150, 400) / 100
            ];
            
            $result = $this->trackConversion($conversionData);
            $results[] = array_merge($conversionData, $result);
        }
        
        return $results;
    }
    
    /**
     * Get ROI performance dashboard
     */
    public function getROIDashboard($platform = null, $days = 30, $attributionModel = 'last_click') {
        $where = ["attribution_date >= DATE('now', '-$days days')", "attribution_model = ?"];
        $params = [$attributionModel];
        
        if ($platform) {
            $where[] = "platform = ?";
            $params[] = $platform;
        }
        
        $whereClause = implode(" AND ", $where);
        
        // Overall metrics
        $stmt = $this->db->prepare("
            SELECT 
                SUM(revenue) as total_revenue,
                SUM(cost) as total_cost,
                SUM(conversions) as total_conversions,
                SUM(sessions) as total_sessions,
                AVG(roi_percentage) as avg_roi,
                AVG(roas) as avg_roas,
                AVG(conversion_rate) as avg_conversion_rate,
                AVG(customer_lifetime_value) as avg_clv
            FROM social_roi_attribution
            WHERE $whereClause
        ");
        
        $stmt->execute($params);
        $overallMetrics = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Platform performance
        $stmt = $this->db->prepare("
            SELECT 
                platform,
                SUM(revenue) as revenue,
                SUM(cost) as cost,
                SUM(conversions) as conversions,
                SUM(sessions) as sessions,
                AVG(roi_percentage) as avg_roi,
                AVG(roas) as avg_roas,
                AVG(conversion_rate) as avg_conversion_rate
            FROM social_roi_attribution
            WHERE $whereClause
            GROUP BY platform
            ORDER BY revenue DESC
        ");
        
        $stmt->execute($params);
        $platformPerformance = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Campaign performance
        $stmt = $this->db->prepare("
            SELECT 
                utm_campaign,
                platform,
                SUM(revenue) as revenue,
                SUM(cost) as cost,
                SUM(conversions) as conversions,
                AVG(roi_percentage) as avg_roi,
                AVG(roas) as avg_roas
            FROM social_roi_attribution
            WHERE $whereClause AND utm_campaign IS NOT NULL
            GROUP BY utm_campaign, platform
            ORDER BY revenue DESC
            LIMIT 20
        ");
        
        $stmt->execute($params);
        $campaignPerformance = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Daily trends
        $stmt = $this->db->prepare("
            SELECT 
                attribution_date,
                SUM(revenue) as daily_revenue,
                SUM(cost) as daily_cost,
                SUM(conversions) as daily_conversions,
                AVG(roi_percentage) as daily_roi
            FROM social_roi_attribution
            WHERE $whereClause
            GROUP BY attribution_date
            ORDER BY attribution_date DESC
        ");
        
        $stmt->execute($params);
        $dailyTrends = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        return [
            'overall_metrics' => $overallMetrics,
            'platform_performance' => $platformPerformance,
            'campaign_performance' => $campaignPerformance,
            'daily_trends' => $dailyTrends,
            'attribution_model' => $attributionModel,
            'analysis_period' => $days
        ];
    }
    
    /**
     * Compare attribution models
     */
    public function compareAttributionModels($platform = null, $days = 30) {
        $where = ["attribution_date >= DATE('now', '-$days days')"];
        $params = [];
        
        if ($platform) {
            $where[] = "platform = ?";
            $params[] = $platform;
        }
        
        $whereClause = implode(" AND ", $where);
        
        $stmt = $this->db->prepare("
            SELECT 
                attribution_model,
                SUM(revenue) as total_revenue,
                SUM(cost) as total_cost,
                SUM(conversions) as total_conversions,
                AVG(roi_percentage) as avg_roi,
                AVG(roas) as avg_roas,
                COUNT(*) as attribution_count
            FROM social_roi_attribution
            WHERE $whereClause
            GROUP BY attribution_model
            ORDER BY total_revenue DESC
        ");
        
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Get customer journey analysis
     */
    public function getCustomerJourneyAnalysis($platform = null, $days = 30) {
        $where = ["attribution_date >= DATE('now', '-$days days')"];
        $params = [];
        
        if ($platform) {
            $where[] = "platform = ?";
            $params[] = $platform;
        }
        
        $whereClause = implode(" AND ", $where);
        
        // Journey paths analysis
        $stmt = $this->db->prepare("
            SELECT 
                utm_source,
                utm_medium,
                COUNT(*) as touchpoints,
                SUM(conversions) as total_conversions,
                AVG(customer_lifetime_value) as avg_clv,
                AVG(avg_session_duration) as avg_duration
            FROM social_roi_attribution
            WHERE $whereClause AND attribution_model = 'last_click'
            GROUP BY utm_source, utm_medium
            ORDER BY total_conversions DESC
        ");
        
        $stmt->execute($params);
        $journeyPaths = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Session behavior analysis
        $stmt = $this->db->prepare("
            SELECT 
                CASE 
                    WHEN avg_session_duration < 60 THEN 'Quick (< 1 min)'
                    WHEN avg_session_duration < 300 THEN 'Medium (1-5 min)'
                    ELSE 'Long (> 5 min)'
                END as session_length,
                AVG(conversion_rate) as avg_conversion_rate,
                AVG(bounce_rate) as avg_bounce_rate,
                COUNT(*) as session_count
            FROM social_roi_attribution
            WHERE $whereClause
            GROUP BY 
                CASE 
                    WHEN avg_session_duration < 60 THEN 'Quick (< 1 min)'
                    WHEN avg_session_duration < 300 THEN 'Medium (1-5 min)'
                    ELSE 'Long (> 5 min)'
                END
            ORDER BY avg_conversion_rate DESC
        ");
        
        $stmt->execute($params);
        $sessionBehavior = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        return [
            'journey_paths' => $journeyPaths,
            'session_behavior' => $sessionBehavior
        ];
    }
    
    /**
     * Calculate incremental lift from social media
     */
    public function calculateIncrementalLift($testPeriodDays = 30, $controlPeriodDays = 30) {
        // Compare test period vs control period
        $testStart = date('Y-m-d', strtotime("-$testPeriodDays days"));
        $controlEnd = date('Y-m-d', strtotime("-" . ($testPeriodDays + 1) . " days"));
        $controlStart = date('Y-m-d', strtotime("-" . ($testPeriodDays + $controlPeriodDays) . " days"));
        
        // Test period metrics
        $stmt = $this->db->prepare("
            SELECT 
                SUM(revenue) as revenue,
                SUM(conversions) as conversions,
                SUM(sessions) as sessions
            FROM social_roi_attribution
            WHERE attribution_date >= ? AND attribution_model = 'last_click'
        ");
        $stmt->execute([$testStart]);
        $testMetrics = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Control period metrics
        $stmt = $this->db->prepare("
            SELECT 
                SUM(revenue) as revenue,
                SUM(conversions) as conversions,
                SUM(sessions) as sessions
            FROM social_roi_attribution
            WHERE attribution_date >= ? AND attribution_date <= ? AND attribution_model = 'last_click'
        ");
        $stmt->execute([$controlStart, $controlEnd]);
        $controlMetrics = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Calculate lift
        $revenueLift = $controlMetrics['revenue'] > 0 ? 
            round((($testMetrics['revenue'] - $controlMetrics['revenue']) / $controlMetrics['revenue']) * 100, 2) : 0;
        
        $conversionLift = $controlMetrics['conversions'] > 0 ? 
            round((($testMetrics['conversions'] - $controlMetrics['conversions']) / $controlMetrics['conversions']) * 100, 2) : 0;
        
        return [
            'test_period' => $testMetrics,
            'control_period' => $controlMetrics,
            'revenue_lift_percentage' => $revenueLift,
            'conversion_lift_percentage' => $conversionLift
        ];
    }
    
    /**
     * Generate ROI insights and recommendations
     */
    public function generateROIInsights($platform = null, $days = 30) {
        $dashboard = $this->getROIDashboard($platform, $days);
        $insights = [];
        
        // Revenue insights
        if ($dashboard['overall_metrics']['total_revenue'] > 0) {
            $totalROI = $dashboard['overall_metrics']['avg_roi'];
            
            if ($totalROI > 200) {
                $insights[] = [
                    'type' => 'success',
                    'title' => 'Excellent ROI Performance',
                    'description' => "Your social media campaigns are generating {$totalROI}% ROI",
                    'recommendation' => 'Scale successful campaigns and maintain current strategy'
                ];
            } elseif ($totalROI < 50) {
                $insights[] = [
                    'type' => 'warning',
                    'title' => 'Low ROI Performance',
                    'description' => "ROI is below optimal at {$totalROI}%",
                    'recommendation' => 'Review targeting, creative, and landing page optimization'
                ];
            }
        }
        
        // Platform insights
        $bestPlatform = $dashboard['platform_performance'][0] ?? null;
        if ($bestPlatform) {
            $insights[] = [
                'type' => 'opportunity',
                'title' => 'Top Performing Platform',
                'description' => "{$bestPlatform['platform']} is your highest revenue generator",
                'recommendation' => 'Consider increasing budget allocation to this platform'
            ];
        }
        
        // Campaign insights
        $topCampaign = $dashboard['campaign_performance'][0] ?? null;
        if ($topCampaign) {
            $insights[] = [
                'type' => 'trend',
                'title' => 'Top Campaign',
                'description' => "'{$topCampaign['utm_campaign']}' campaign is driving the most revenue",
                'recommendation' => 'Analyze and replicate this campaign\'s success factors'
            ];
        }
        
        return $insights;
    }
    
    /**
     * Initialize attribution models
     */
    private function initializeAttributionModels() {
        $this->attributionModels = [
            'first_click',
            'last_click',
            'linear',
            'time_decay',
            'position_based'
        ];
    }
    
    /**
     * Export ROI data for external analysis
     */
    public function exportROIData($format = 'json', $platform = null, $days = 30) {
        $dashboard = $this->getROIDashboard($platform, $days);
        
        if ($format === 'csv') {
            return $this->convertToCSV($dashboard['platform_performance']);
        }
        
        return json_encode($dashboard, JSON_PRETTY_PRINT);
    }
    
    /**
     * Convert data to CSV format
     */
    private function convertToCSV($data) {
        if (empty($data)) return '';
        
        $headers = array_keys($data[0]);
        $csv = implode(',', $headers) . "\n";
        
        foreach ($data as $row) {
            $csv .= implode(',', array_values($row)) . "\n";
        }
        
        return $csv;
    }
}