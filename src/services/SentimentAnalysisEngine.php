<?php
/**
 * Advanced Sentiment Analysis Engine
 * 
 * Provides comprehensive sentiment analysis for social media content including:
 * - Real-time sentiment scoring
 * - Emotion detection (joy, anger, fear, sadness, surprise, disgust)
 * - Keyword sentiment analysis
 * - Confidence scoring
 * - Multiple provider support (internal, external APIs)
 */

require_once __DIR__ . '/../lib/db.php';

class SentimentAnalysisEngine {
    private $db;
    private $emotionKeywords;
    private $sentimentKeywords;
    
    public function __construct() {
        global $db;
        $this->db = $db;
        $this->initializeKeywordMaps();
    }
    
    /**
     * Analyze sentiment for any text content
     */
    public function analyzeSentiment($text, $contentId = null, $postId = null, $platform = 'unknown', $provider = 'internal') {
        $text = $this->preprocessText($text);
        
        // Internal sentiment analysis
        if ($provider === 'internal') {
            $result = $this->performInternalAnalysis($text);
        } else {
            // External API analysis (placeholder for future implementation)
            $result = $this->performExternalAnalysis($text, $provider);
        }
        
        // Store results
        $this->storeSentimentResult($result, $contentId, $postId, $platform, $text, $provider);
        
        return $result;
    }
    
    /**
     * Perform internal sentiment analysis using keyword-based approach
     */
    private function performInternalAnalysis($text) {
        $words = $this->tokenizeText($text);
        $totalWords = count($words);
        
        if ($totalWords === 0) {
            return $this->createSentimentResult(0.0, 'neutral', 0.5, [], []);
        }
        
        $sentimentScore = 0;
        $emotionScores = ['joy' => 0, 'anger' => 0, 'fear' => 0, 'sadness' => 0, 'surprise' => 0, 'disgust' => 0];
        $sentimentKeywords = [];
        
        foreach ($words as $word) {
            $word = strtolower($word);
            
            // Check sentiment keywords
            if (isset($this->sentimentKeywords[$word])) {
                $score = $this->sentimentKeywords[$word];
                $sentimentScore += $score;
                $sentimentKeywords[] = ['word' => $word, 'score' => $score];
            }
            
            // Check emotion keywords
            foreach ($this->emotionKeywords as $emotion => $keywords) {
                if (in_array($word, $keywords)) {
                    $emotionScores[$emotion]++;
                }
            }
        }
        
        // Normalize sentiment score
        $normalizedScore = $this->normalizeSentimentScore($sentimentScore, $totalWords);
        
        // Determine sentiment label
        $sentimentLabel = $this->getSentimentLabel($normalizedScore);
        
        // Calculate confidence based on keyword matches
        $keywordMatches = count($sentimentKeywords);
        $confidence = min(0.9, ($keywordMatches / max(1, $totalWords)) * 3);
        
        // Get dominant emotions
        $dominantEmotions = $this->getDominantEmotions($emotionScores);
        
        return $this->createSentimentResult($normalizedScore, $sentimentLabel, $confidence, $dominantEmotions, $sentimentKeywords);
    }
    
    /**
     * Placeholder for external API sentiment analysis
     */
    private function performExternalAnalysis($text, $provider) {
        // This would integrate with external APIs like:
        // - Google Cloud Natural Language API
        // - AWS Comprehend
        // - Azure Text Analytics
        // - IBM Watson Natural Language Understanding
        
        // For now, return internal analysis as fallback
        return $this->performInternalAnalysis($text);
    }
    
    /**
     * Preprocess text for analysis
     */
    private function preprocessText($text) {
        // Remove URLs
        $text = preg_replace('/https?:\/\/[^\s]+/', '', $text);
        
        // Remove mentions and hashtags for cleaner analysis
        $text = preg_replace('/@[\w]+/', '', $text);
        $text = preg_replace('/#[\w]+/', '', $text);
        
        // Clean up whitespace
        $text = preg_replace('/\s+/', ' ', trim($text));
        
        return $text;
    }
    
    /**
     * Tokenize text into words
     */
    private function tokenizeText($text) {
        // Simple word tokenization
        $words = preg_split('/\W+/', strtolower($text), -1, PREG_SPLIT_NO_EMPTY);
        return $words;
    }
    
    /**
     * Normalize sentiment score to -1.0 to 1.0 range
     */
    private function normalizeSentimentScore($rawScore, $wordCount) {
        if ($wordCount === 0) return 0.0;
        
        // Average score per word, then apply sigmoid-like normalization
        $avgScore = $rawScore / $wordCount;
        $normalized = tanh($avgScore); // tanh provides nice -1 to 1 curve
        
        return round($normalized, 3);
    }
    
    /**
     * Get sentiment label from numeric score
     */
    private function getSentimentLabel($score) {
        if ($score <= -0.6) return 'very_negative';
        if ($score <= -0.2) return 'negative';
        if ($score >= 0.6) return 'very_positive';
        if ($score >= 0.2) return 'positive';
        return 'neutral';
    }
    
    /**
     * Get dominant emotions from emotion scores
     */
    private function getDominantEmotions($emotionScores) {
        $total = array_sum($emotionScores);
        if ($total === 0) return [];
        
        $dominantEmotions = [];
        foreach ($emotionScores as $emotion => $count) {
            if ($count > 0) {
                $percentage = round(($count / $total) * 100, 1);
                if ($percentage >= 10) { // Only include emotions above 10%
                    $dominantEmotions[] = ['emotion' => $emotion, 'percentage' => $percentage];
                }
            }
        }
        
        // Sort by percentage
        usort($dominantEmotions, function($a, $b) {
            return $b['percentage'] <=> $a['percentage'];
        });
        
        return $dominantEmotions;
    }
    
    /**
     * Create standardized sentiment result
     */
    private function createSentimentResult($score, $label, $confidence, $emotions, $keywords) {
        return [
            'sentiment_score' => $score,
            'sentiment_label' => $label,
            'confidence_score' => $confidence,
            'emotions' => $emotions,
            'keywords' => $keywords
        ];
    }
    
    /**
     * Store sentiment analysis result in database
     */
    private function storeSentimentResult($result, $contentId, $postId, $platform, $text, $provider) {
        $stmt = $this->db->prepare("
            INSERT INTO sentiment_analysis (
                content_id, post_id, platform, content_text, 
                sentiment_score, sentiment_label, confidence_score, 
                emotions, keywords, analysis_provider
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        
        $stmt->execute([
            $contentId,
            $postId,
            $platform,
            $text,
            $result['sentiment_score'],
            $result['sentiment_label'],
            $result['confidence_score'],
            json_encode($result['emotions']),
            json_encode($result['keywords']),
            $provider
        ]);
        
        return $this->db->lastInsertId();
    }
    
    /**
     * Get sentiment analysis history for a post or content
     */
    public function getSentimentHistory($postId = null, $contentId = null, $limit = 50) {
        $where = [];
        $params = [];
        
        if ($postId) {
            $where[] = "post_id = ?";
            $params[] = $postId;
        }
        
        if ($contentId) {
            $where[] = "content_id = ?";
            $params[] = $contentId;
        }
        
        $whereClause = $where ? "WHERE " . implode(" AND ", $where) : "";
        
        $stmt = $this->db->prepare("
            SELECT * FROM sentiment_analysis 
            $whereClause
            ORDER BY analyzed_at DESC 
            LIMIT ?
        ");
        
        $params[] = $limit;
        $stmt->execute($params);
        
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Decode JSON fields
        foreach ($results as &$result) {
            $result['emotions'] = json_decode($result['emotions'], true) ?: [];
            $result['keywords'] = json_decode($result['keywords'], true) ?: [];
        }
        
        return $results;
    }
    
    /**
     * Get sentiment trends over time
     */
    public function getSentimentTrends($platform = null, $days = 30) {
        $where = "WHERE analyzed_at >= DATE('now', '-$days days')";
        if ($platform) {
            $where .= " AND platform = '$platform'";
        }
        
        $stmt = $this->db->query("
            SELECT 
                DATE(analyzed_at) as date,
                AVG(sentiment_score) as avg_sentiment,
                COUNT(*) as total_analyses,
                COUNT(CASE WHEN sentiment_label IN ('positive', 'very_positive') THEN 1 END) as positive_count,
                COUNT(CASE WHEN sentiment_label = 'neutral' THEN 1 END) as neutral_count,
                COUNT(CASE WHEN sentiment_label IN ('negative', 'very_negative') THEN 1 END) as negative_count
            FROM sentiment_analysis 
            $where
            GROUP BY DATE(analyzed_at)
            ORDER BY date DESC
        ");
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Analyze sentiment for multiple social media posts
     */
    public function analyzeBulkPosts($limit = 100) {
        // Get unanalyzed posts
        $stmt = $this->db->prepare("
            SELECT p.id, p.content, p.platform, p.created_at
            FROM social_media_posts p
            LEFT JOIN sentiment_analysis s ON p.id = s.post_id
            WHERE s.id IS NULL
            ORDER BY p.created_at DESC
            LIMIT ?
        ");
        
        $stmt->execute([$limit]);
        $posts = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $results = [];
        foreach ($posts as $post) {
            if (!empty($post['content'])) {
                $result = $this->analyzeSentiment(
                    $post['content'],
                    null,
                    $post['id'],
                    $post['platform']
                );
                $results[] = array_merge($post, $result);
            }
        }
        
        return $results;
    }
    
    /**
     * Initialize sentiment and emotion keyword mappings
     */
    private function initializeKeywordMaps() {
        // Sentiment keywords with scores (-3 to 3)
        $this->sentimentKeywords = [
            // Very positive
            'amazing' => 3, 'awesome' => 3, 'fantastic' => 3, 'excellent' => 3, 'perfect' => 3,
            'outstanding' => 3, 'incredible' => 3, 'brilliant' => 3, 'wonderful' => 3, 'superb' => 3,
            
            // Positive
            'good' => 2, 'great' => 2, 'nice' => 2, 'happy' => 2, 'love' => 2,
            'like' => 1, 'enjoy' => 2, 'pleased' => 2, 'satisfied' => 2, 'glad' => 2,
            
            // Slightly positive
            'okay' => 1, 'fine' => 1, 'decent' => 1, 'alright' => 1,
            
            // Negative
            'bad' => -2, 'terrible' => -3, 'awful' => -3, 'horrible' => -3, 'hate' => -3,
            'dislike' => -2, 'disappointed' => -2, 'frustrated' => -2, 'angry' => -2, 'sad' => -2,
            
            // Very negative
            'disgusting' => -3, 'pathetic' => -3, 'worthless' => -3, 'useless' => -3, 'disaster' => -3,
            
            // Neutral but contextual
            'maybe' => 0, 'perhaps' => 0, 'could' => 0, 'might' => 0
        ];
        
        // Emotion keywords
        $this->emotionKeywords = [
            'joy' => ['happy', 'excited', 'thrilled', 'delighted', 'cheerful', 'joyful', 'ecstatic', 'elated', 'pleased', 'glad'],
            'anger' => ['angry', 'mad', 'furious', 'irritated', 'annoyed', 'frustrated', 'outraged', 'livid', 'irate', 'pissed'],
            'fear' => ['scared', 'afraid', 'terrified', 'worried', 'anxious', 'nervous', 'fearful', 'panicked', 'concerned', 'apprehensive'],
            'sadness' => ['sad', 'depressed', 'unhappy', 'disappointed', 'heartbroken', 'devastated', 'miserable', 'grief', 'sorrow', 'melancholy'],
            'surprise' => ['surprised', 'shocked', 'amazed', 'astonished', 'stunned', 'bewildered', 'startled', 'confused', 'unexpected', 'wow'],
            'disgust' => ['disgusting', 'gross', 'revolting', 'nauseating', 'repulsive', 'sickening', 'appalling', 'horrible', 'awful', 'yuck']
        ];
    }
    
    /**
     * Get platform-specific sentiment insights
     */
    public function getPlatformSentimentInsights($platform, $days = 30) {
        $stmt = $this->db->prepare("
            SELECT 
                sentiment_label,
                COUNT(*) as count,
                AVG(sentiment_score) as avg_score,
                AVG(confidence_score) as avg_confidence
            FROM sentiment_analysis 
            WHERE platform = ? AND analyzed_at >= DATE('now', '-$days days')
            GROUP BY sentiment_label
            ORDER BY count DESC
        ");
        
        $stmt->execute([$platform]);
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $total = array_sum(array_column($results, 'count'));
        
        foreach ($results as &$result) {
            $result['percentage'] = $total > 0 ? round(($result['count'] / $total) * 100, 1) : 0;
        }
        
        return $results;
    }
}