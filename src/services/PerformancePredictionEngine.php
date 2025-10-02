<?php
/**
 * Performance Prediction Engine
 * 
 * AI-powered forecasting system for social media performance including:
 * - Engagement prediction
 * - Reach forecasting
 * - Conversion prediction
 * - Revenue forecasting
 * - Sentiment prediction
 * - Trend analysis and anomaly detection
 */

require_once __DIR__ . '/../lib/db.php';

class PerformancePredictionEngine {
    private $db;
    private $models;
    
    public function __construct() {
        global $db;
        $this->db = $db;
        $this->initializePredictionModels();
    }
    
    /**
     * Generate prediction for specific metric
     */
    public function generatePrediction($modelType, $platform, $inputFeatures, $horizonDays = 7, $accountId = null) {
        // Validate model type
        if (!in_array($modelType, ['engagement', 'reach', 'conversion', 'revenue', 'sentiment'])) {
            throw new Exception("Invalid model type: $modelType");
        }
        
        // Get historical data for training
        $historicalData = $this->getHistoricalData($modelType, $platform, $accountId);
        
        // Generate prediction based on model type
        $prediction = $this->executeModel($modelType, $inputFeatures, $historicalData, $horizonDays);
        
        // Store prediction in database
        $predictionId = $this->storePrediction(
            $modelType,
            $platform,
            $inputFeatures,
            $prediction,
            $horizonDays,
            $accountId
        );
        
        return array_merge($prediction, ['prediction_id' => $predictionId]);
    }
    
    /**
     * Execute specific prediction model
     */
    private function executeModel($modelType, $inputFeatures, $historicalData, $horizonDays) {
        switch ($modelType) {
            case 'engagement':
                return $this->predictEngagement($inputFeatures, $historicalData, $horizonDays);
            case 'reach':
                return $this->predictReach($inputFeatures, $historicalData, $horizonDays);
            case 'conversion':
                return $this->predictConversion($inputFeatures, $historicalData, $horizonDays);
            case 'revenue':
                return $this->predictRevenue($inputFeatures, $historicalData, $horizonDays);
            case 'sentiment':
                return $this->predictSentiment($inputFeatures, $historicalData, $horizonDays);
            default:
                throw new Exception("Model not implemented: $modelType");
        }
    }
    
    /**
     * Predict engagement metrics
     */
    private function predictEngagement($features, $historicalData, $horizonDays) {
        // Extract features
        $followerCount = $features['follower_count'] ?? 1000;
        $postType = $features['post_type'] ?? 'text';
        $hasImage = $features['has_image'] ?? false;
        $hasVideo = $features['has_video'] ?? false;
        $hashtagCount = $features['hashtag_count'] ?? 0;
        $postLength = $features['post_length'] ?? 100;
        $postingTime = $features['posting_time'] ?? 12; // hour of day
        $dayOfWeek = $features['day_of_week'] ?? 1;
        
        // Calculate base engagement from historical data
        $baseEngagement = $this->calculateAverageEngagement($historicalData);
        
        // Apply feature multipliers (simplified ML approach)
        $multiplier = 1.0;
        
        // Post type multipliers
        $typeMultipliers = [
            'text' => 1.0,
            'image' => 1.3,
            'video' => 1.8,
            'carousel' => 1.5,
            'story' => 0.9
        ];
        $multiplier *= $typeMultipliers[$postType] ?? 1.0;
        
        // Media multipliers
        if ($hasImage) $multiplier *= 1.25;
        if ($hasVideo) $multiplier *= 1.6;
        
        // Hashtag optimization
        if ($hashtagCount >= 3 && $hashtagCount <= 10) {
            $multiplier *= 1.15;
        } elseif ($hashtagCount > 15) {
            $multiplier *= 0.9; // Too many hashtags
        }
        
        // Post length optimization
        if ($postLength >= 100 && $postLength <= 300) {
            $multiplier *= 1.1; // Optimal length
        } elseif ($postLength > 500) {
            $multiplier *= 0.85; // Too long
        }
        
        // Time-based multipliers
        $timeMultipliers = [
            9 => 1.1, 10 => 1.2, 11 => 1.3, 12 => 1.4, // Morning peak
            13 => 1.3, 14 => 1.2, 15 => 1.1,           // Afternoon
            17 => 1.2, 18 => 1.4, 19 => 1.5, 20 => 1.3 // Evening peak
        ];
        $multiplier *= $timeMultipliers[$postingTime] ?? 1.0;
        
        // Day of week multipliers (1=Monday, 7=Sunday)
        $dayMultipliers = [1 => 1.1, 2 => 1.2, 3 => 1.3, 4 => 1.2, 5 => 1.1, 6 => 0.9, 7 => 0.8];
        $multiplier *= $dayMultipliers[$dayOfWeek] ?? 1.0;
        
        // Follower count scaling
        $followerMultiplier = log10($followerCount / 1000 + 1) * 0.5 + 1;
        $multiplier *= $followerMultiplier;
        
        // Calculate predicted engagement
        $predictedEngagement = round($baseEngagement * $multiplier);
        
        // Add some variance for confidence intervals
        $variance = $predictedEngagement * 0.3;
        $confidenceMin = max(0, $predictedEngagement - $variance);
        $confidenceMax = $predictedEngagement + $variance;
        
        // Calculate confidence score based on data quality
        $confidence = $this->calculateConfidence($historicalData, count($features));
        
        return [
            'prediction_value' => $predictedEngagement,
            'confidence_interval' => ['min' => $confidenceMin, 'max' => $confidenceMax],
            'confidence_score' => $confidence,
            'model_version' => '1.0'
        ];
    }
    
    /**
     * Predict reach metrics
     */
    private function predictReach($features, $historicalData, $horizonDays) {
        $baseReach = $this->calculateAverageReach($historicalData);
        $followerCount = $features['follower_count'] ?? 1000;
        
        // Reach is typically 10-30% of followers
        $reachRate = 0.15; // 15% average
        
        // Adjust based on engagement prediction
        $engagementPrediction = $this->predictEngagement($features, $historicalData, $horizonDays);
        $reachMultiplier = ($engagementPrediction['prediction_value'] / max(1, $baseReach)) * 0.5 + 0.75;
        
        $predictedReach = round($followerCount * $reachRate * $reachMultiplier);
        
        $variance = $predictedReach * 0.4;
        $confidence = $this->calculateConfidence($historicalData, count($features));
        
        return [
            'prediction_value' => $predictedReach,
            'confidence_interval' => [
                'min' => max(0, $predictedReach - $variance),
                'max' => $predictedReach + $variance
            ],
            'confidence_score' => $confidence,
            'model_version' => '1.0'
        ];
    }
    
    /**
     * Predict conversion metrics
     */
    private function predictConversion($features, $historicalData, $horizonDays) {
        $baseConversionRate = $this->calculateAverageConversionRate($historicalData);
        $trafficPrediction = $features['expected_traffic'] ?? 100;
        
        // Factors affecting conversion
        $landingPageQuality = $features['landing_page_score'] ?? 0.7;
        $targetingQuality = $features['targeting_score'] ?? 0.8;
        $offerStrength = $features['offer_score'] ?? 0.6;
        
        // Calculate conversion rate multiplier
        $conversionMultiplier = ($landingPageQuality + $targetingQuality + $offerStrength) / 3;
        
        $predictedConversionRate = $baseConversionRate * $conversionMultiplier;
        $predictedConversions = round($trafficPrediction * $predictedConversionRate / 100);
        
        $variance = $predictedConversions * 0.5;
        $confidence = $this->calculateConfidence($historicalData, count($features));
        
        return [
            'prediction_value' => $predictedConversions,
            'conversion_rate' => $predictedConversionRate,
            'confidence_interval' => [
                'min' => max(0, $predictedConversions - $variance),
                'max' => $predictedConversions + $variance
            ],
            'confidence_score' => $confidence,
            'model_version' => '1.0'
        ];
    }
    
    /**
     * Predict revenue metrics
     */
    private function predictRevenue($features, $historicalData, $horizonDays) {
        $conversionPrediction = $this->predictConversion($features, $historicalData, $horizonDays);
        $avgOrderValue = $features['avg_order_value'] ?? 100;
        $seasonalMultiplier = $features['seasonal_multiplier'] ?? 1.0;
        
        $predictedRevenue = $conversionPrediction['prediction_value'] * $avgOrderValue * $seasonalMultiplier;
        
        $variance = $predictedRevenue * 0.3;
        $confidence = $this->calculateConfidence($historicalData, count($features));
        
        return [
            'prediction_value' => round($predictedRevenue, 2),
            'conversions' => $conversionPrediction['prediction_value'],
            'confidence_interval' => [
                'min' => max(0, $predictedRevenue - $variance),
                'max' => $predictedRevenue + $variance
            ],
            'confidence_score' => $confidence,
            'model_version' => '1.0'
        ];
    }
    
    /**
     * Predict sentiment metrics
     */
    private function predictSentiment($features, $historicalData, $horizonDays) {
        $baseSentiment = $this->calculateAverageSentiment($historicalData);
        
        // Factors affecting sentiment
        $contentTone = $features['content_tone'] ?? 'neutral'; // positive, neutral, negative
        $topicSensitivity = $features['topic_sensitivity'] ?? 0.5;
        $brandHealth = $features['brand_health_score'] ?? 0.7;
        
        // Tone multipliers
        $toneMultipliers = [
            'very_positive' => 0.8,
            'positive' => 0.4,
            'neutral' => 0.0,
            'negative' => -0.4,
            'very_negative' => -0.8
        ];
        
        $toneAdjustment = $toneMultipliers[$contentTone] ?? 0;
        $sensitivityAdjustment = ($topicSensitivity - 0.5) * 0.2;
        $brandAdjustment = ($brandHealth - 0.5) * 0.3;
        
        $predictedSentiment = $baseSentiment + $toneAdjustment + $sensitivityAdjustment + $brandAdjustment;
        $predictedSentiment = max(-1, min(1, $predictedSentiment)); // Clamp to -1 to 1
        
        $variance = 0.2;
        $confidence = $this->calculateConfidence($historicalData, count($features));
        
        return [
            'prediction_value' => round($predictedSentiment, 3),
            'confidence_interval' => [
                'min' => max(-1, $predictedSentiment - $variance),
                'max' => min(1, $predictedSentiment + $variance)
            ],
            'confidence_score' => $confidence,
            'model_version' => '1.0'
        ];
    }
    
    /**
     * Calculate average engagement from historical data
     */
    private function calculateAverageEngagement($historicalData) {
        if (empty($historicalData)) return 50; // Default fallback
        
        $totalEngagement = array_sum(array_column($historicalData, 'engagement'));
        return $totalEngagement / count($historicalData);
    }
    
    /**
     * Calculate average reach from historical data
     */
    private function calculateAverageReach($historicalData) {
        if (empty($historicalData)) return 500; // Default fallback
        
        $totalReach = array_sum(array_column($historicalData, 'reach'));
        return $totalReach / count($historicalData);
    }
    
    /**
     * Calculate average conversion rate from historical data
     */
    private function calculateAverageConversionRate($historicalData) {
        if (empty($historicalData)) return 2.5; // Default 2.5%
        
        $rates = array_column($historicalData, 'conversion_rate');
        return array_sum($rates) / count($rates);
    }
    
    /**
     * Calculate average sentiment from historical data
     */
    private function calculateAverageSentiment($historicalData) {
        if (empty($historicalData)) return 0.1; // Slightly positive default
        
        $sentiments = array_column($historicalData, 'sentiment');
        return array_sum($sentiments) / count($sentiments);
    }
    
    /**
     * Calculate confidence score based on data quality
     */
    private function calculateConfidence($historicalData, $featureCount) {
        $dataPoints = count($historicalData);
        $dataQuality = min(1.0, $dataPoints / 100); // More data = higher confidence
        $featureQuality = min(1.0, $featureCount / 10); // More features = higher confidence
        
        return round(($dataQuality + $featureQuality) / 2, 2);
    }
    
    /**
     * Get historical data for model training
     */
    private function getHistoricalData($modelType, $platform, $accountId = null, $days = 90) {
        // Simulate historical data - in real implementation, this would query actual data
        $data = [];
        
        for ($i = 0; $i < min(100, $days); $i++) {
            $data[] = [
                'engagement' => rand(20, 200),
                'reach' => rand(200, 2000),
                'conversion_rate' => rand(100, 500) / 100,
                'sentiment' => rand(-50, 50) / 100,
                'date' => date('Y-m-d', strtotime("-$i days"))
            ];
        }
        
        return $data;
    }
    
    /**
     * Store prediction in database
     */
    private function storePrediction($modelType, $platform, $inputFeatures, $prediction, $horizonDays, $accountId) {
        $stmt = $this->db->prepare("
            INSERT INTO performance_predictions (
                model_name, model_type, platform, account_id, input_features,
                prediction_value, confidence_interval, confidence_score,
                prediction_date, prediction_horizon, model_version
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        
        $stmt->execute([
            $modelType . '_predictor',
            $modelType,
            $platform,
            $accountId,
            json_encode($inputFeatures),
            $prediction['prediction_value'],
            json_encode($prediction['confidence_interval']),
            $prediction['confidence_score'],
            date('Y-m-d'),
            $horizonDays,
            $prediction['model_version']
        ]);
        
        return $this->db->lastInsertId();
    }
    
    /**
     * Get prediction accuracy analysis
     */
    public function getPredictionAccuracy($modelType = null, $days = 30) {
        $where = ["prediction_date >= DATE('now', '-$days days')", "actual_value IS NOT NULL"];
        $params = [];
        
        if ($modelType) {
            $where[] = "model_type = ?";
            $params[] = $modelType;
        }
        
        $whereClause = implode(" AND ", $where);
        
        $stmt = $this->db->prepare("
            SELECT 
                model_type,
                COUNT(*) as prediction_count,
                AVG(accuracy_score) as avg_accuracy,
                AVG(confidence_score) as avg_confidence,
                AVG(ABS(prediction_value - actual_value) / NULLIF(actual_value, 0) * 100) as avg_error_percentage
            FROM performance_predictions
            WHERE $whereClause
            GROUP BY model_type
            ORDER BY avg_accuracy DESC
        ");
        
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Update prediction with actual results
     */
    public function updatePredictionWithActual($predictionId, $actualValue) {
        // Get the prediction
        $stmt = $this->db->prepare("SELECT * FROM performance_predictions WHERE id = ?");
        $stmt->execute([$predictionId]);
        $prediction = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$prediction) {
            throw new Exception("Prediction not found");
        }
        
        // Calculate accuracy
        $predictedValue = $prediction['prediction_value'];
        $accuracy = $predictedValue > 0 ? 1 - abs($predictedValue - $actualValue) / $predictedValue : 0;
        $accuracy = max(0, min(1, $accuracy)); // Clamp between 0 and 1
        
        // Update the prediction
        $stmt = $this->db->prepare("
            UPDATE performance_predictions 
            SET actual_value = ?, accuracy_score = ?, validated_at = CURRENT_TIMESTAMP
            WHERE id = ?
        ");
        
        $stmt->execute([$actualValue, $accuracy, $predictionId]);
        
        return [
            'predicted_value' => $predictedValue,
            'actual_value' => $actualValue,
            'accuracy_score' => $accuracy,
            'error_percentage' => abs($predictedValue - $actualValue) / max(1, $actualValue) * 100
        ];
    }
    
    /**
     * Generate batch predictions for multiple scenarios
     */
    public function generateBatchPredictions($scenarios) {
        $results = [];
        
        foreach ($scenarios as $scenario) {
            try {
                $prediction = $this->generatePrediction(
                    $scenario['model_type'],
                    $scenario['platform'],
                    $scenario['input_features'],
                    $scenario['horizon_days'] ?? 7,
                    $scenario['account_id'] ?? null
                );
                
                $results[] = array_merge($scenario, ['prediction' => $prediction, 'status' => 'success']);
            } catch (Exception $e) {
                $results[] = array_merge($scenario, ['error' => $e->getMessage(), 'status' => 'error']);
            }
        }
        
        return $results;
    }
    
    /**
     * Get prediction trends and insights
     */
    public function getPredictionTrends($platform = null, $days = 30) {
        $where = ["prediction_date >= DATE('now', '-$days days')"];
        $params = [];
        
        if ($platform) {
            $where[] = "platform = ?";
            $params[] = $platform;
        }
        
        $whereClause = implode(" AND ", $where);
        
        $stmt = $this->db->prepare("
            SELECT 
                DATE(prediction_date) as date,
                model_type,
                AVG(prediction_value) as avg_prediction,
                AVG(confidence_score) as avg_confidence,
                COUNT(*) as prediction_count
            FROM performance_predictions
            WHERE $whereClause
            GROUP BY DATE(prediction_date), model_type
            ORDER BY date DESC, model_type
        ");
        
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Initialize prediction models
     */
    private function initializePredictionModels() {
        $this->models = [
            'engagement' => [
                'features' => ['follower_count', 'post_type', 'has_image', 'has_video', 'hashtag_count', 'post_length', 'posting_time', 'day_of_week'],
                'target' => 'engagement_count'
            ],
            'reach' => [
                'features' => ['follower_count', 'engagement_rate', 'post_type', 'boost_amount'],
                'target' => 'reach_count'
            ],
            'conversion' => [
                'features' => ['expected_traffic', 'landing_page_score', 'targeting_score', 'offer_score'],
                'target' => 'conversion_count'
            ],
            'revenue' => [
                'features' => ['expected_conversions', 'avg_order_value', 'seasonal_multiplier'],
                'target' => 'revenue_amount'
            ],
            'sentiment' => [
                'features' => ['content_tone', 'topic_sensitivity', 'brand_health_score'],
                'target' => 'sentiment_score'
            ]
        ];
    }
}