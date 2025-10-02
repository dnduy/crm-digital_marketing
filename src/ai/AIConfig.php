<?php

namespace AI;

/**
 * AI Configuration Manager
 * Manages AI provider configurations and settings
 */
class AIConfig
{
    private array $config;
    private string $configPath;

    public function __construct(string $configPath = null)
    {
        $this->configPath = $configPath ?: __DIR__ . '/../config/ai_config.json';
        $this->loadConfig();
    }

    /**
     * Get complete configuration
     */
    public function getConfig(): array
    {
        return $this->config;
    }

    /**
     * Get provider configuration
     */
    public function getProviderConfig(string $provider): array
    {
        return $this->config['providers'][$provider] ?? [];
    }

    /**
     * Update provider configuration
     */
    public function updateProviderConfig(string $provider, array $config): void
    {
        $this->config['providers'][$provider] = array_merge(
            $this->config['providers'][$provider] ?? [],
            $config
        );
        
        $this->saveConfig();
    }

    /**
     * Enable/disable provider
     */
    public function setProviderEnabled(string $provider, bool $enabled): void
    {
        if (!isset($this->config['providers'][$provider])) {
            $this->config['providers'][$provider] = [];
        }
        
        $this->config['providers'][$provider]['enabled'] = $enabled;
        $this->saveConfig();
    }

    /**
     * Get feature settings
     */
    public function getFeatureSettings(): array
    {
        return $this->config['features'] ?? [];
    }

    /**
     * Update feature settings
     */
    public function updateFeatureSettings(array $features): void
    {
        $this->config['features'] = array_merge(
            $this->config['features'] ?? [],
            $features
        );
        
        $this->saveConfig();
    }

    /**
     * Get default configuration
     */
    public static function getDefaultConfig(): array
    {
        return [
            'providers' => [
                'openai' => [
                    'enabled' => false,
                    'api_key' => '',
                    'model' => 'gpt-4',
                    'max_tokens' => 4096,
                    'temperature' => 0.7,
                    'timeout' => 60,
                    'retry_attempts' => 3
                ],
                'claude' => [
                    'enabled' => false,
                    'api_key' => '',
                    'model' => 'claude-3-sonnet-20240229',
                    'max_tokens' => 4000,
                    'temperature' => 0.7,
                    'timeout' => 60,
                    'retry_attempts' => 3
                ],
                'gemini' => [
                    'enabled' => false,
                    'api_key' => '',
                    'model' => 'gemini-pro',
                    'max_tokens' => 8192,
                    'temperature' => 0.7,
                    'timeout' => 60,
                    'retry_attempts' => 3
                ]
            ],
            'features' => [
                'auto_provider_selection' => true,
                'load_balancing' => true,
                'retry_on_failure' => true,
                'usage_tracking' => true,
                'cost_monitoring' => true,
                'quality_scoring' => true,
                'content_caching' => true,
                'rate_limiting' => true
            ],
            'content_generation' => [
                'default_language' => 'vi',
                'default_tone' => 'professional',
                'max_content_length' => 5000,
                'enable_seo_optimization' => true,
                'enable_sentiment_analysis' => true,
                'auto_social_media_generation' => true
            ],
            'quality_control' => [
                'minimum_content_length' => 50,
                'profanity_filter' => true,
                'fact_checking' => false,
                'originality_check' => true,
                'brand_voice_consistency' => true
            ],
            'rate_limits' => [
                'requests_per_minute' => 60,
                'requests_per_hour' => 1000,
                'requests_per_day' => 10000,
                'concurrent_requests' => 5
            ],
            'caching' => [
                'enabled' => true,
                'ttl' => 3600, // 1 hour
                'max_cache_size' => 1000,
                'cache_similar_prompts' => true,
                'similarity_threshold' => 0.85
            ],
            'monitoring' => [
                'log_all_requests' => true,
                'performance_tracking' => true,
                'error_tracking' => true,
                'cost_alerts' => true,
                'usage_reports' => true
            ]
        ];
    }

    /**
     * Load configuration from file
     */
    private function loadConfig(): void
    {
        if (file_exists($this->configPath)) {
            $configContent = file_get_contents($this->configPath);
            $this->config = json_decode($configContent, true) ?: [];
        } else {
            $this->config = self::getDefaultConfig();
            $this->saveConfig();
        }
        
        // Ensure all default keys exist
        $this->config = array_merge_recursive(self::getDefaultConfig(), $this->config);
    }

    /**
     * Save configuration to file
     */
    private function saveConfig(): void
    {
        $configDir = dirname($this->configPath);
        if (!is_dir($configDir)) {
            mkdir($configDir, 0755, true);
        }
        
        file_put_contents(
            $this->configPath, 
            json_encode($this->config, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)
        );
    }
}

/**
 * AI Quality Manager
 * Manages content quality assessment and improvement
 */
class AIQualityManager
{
    private AIProviderFactory $factory;
    private array $qualityMetrics;
    private array $config;

    public function __construct(AIProviderFactory $factory, array $config = [])
    {
        $this->factory = $factory;
        $this->config = $config;
        $this->qualityMetrics = [];
    }

    /**
     * Assess content quality
     */
    public function assessQuality(string $content, array $criteria = []): array
    {
        $metrics = [];
        
        // Basic metrics
        $metrics['length'] = strlen($content);
        $metrics['word_count'] = str_word_count($content);
        $metrics['readability'] = $this->calculateReadability($content);
        $metrics['seo_score'] = $this->calculateSEOScore($content);
        
        // AI-powered metrics
        if ($this->config['enable_sentiment_analysis'] ?? true) {
            $metrics['sentiment'] = $this->analyzeSentiment($content);
        }
        
        if ($this->config['originality_check'] ?? true) {
            $metrics['originality'] = $this->checkOriginality($content);
        }
        
        // Overall quality score
        $metrics['overall_score'] = $this->calculateOverallScore($metrics);
        $metrics['grade'] = $this->getQualityGrade($metrics['overall_score']);
        
        // Improvement suggestions
        $metrics['suggestions'] = $this->generateImprovementSuggestions($content, $metrics);
        
        return $metrics;
    }

    /**
     * Improve content based on quality assessment
     */
    public function improveContent(string $content, array $targetMetrics = []): array
    {
        $currentQuality = $this->assessQuality($content);
        
        // Determine improvement strategy
        $improvementPrompt = $this->buildImprovementPrompt($content, $currentQuality, $targetMetrics);
        
        try {
            $provider = $this->factory->getBestProvider('content_improvement');
            $improvedContent = $provider->improveContent($content);
            
            $newQuality = $this->assessQuality($improvedContent);
            
            return [
                'success' => true,
                'original_content' => $content,
                'improved_content' => $improvedContent,
                'original_quality' => $currentQuality,
                'improved_quality' => $newQuality,
                'improvement_score' => $newQuality['overall_score'] - $currentQuality['overall_score']
            ];
            
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
                'original_quality' => $currentQuality
            ];
        }
    }

    /**
     * Calculate readability score
     */
    private function calculateReadability(string $content): array
    {
        $sentences = preg_split('/[.!?]+/', $content, -1, PREG_SPLIT_NO_EMPTY);
        $words = str_word_count($content);
        $syllables = $this->countSyllables($content);
        
        $sentenceCount = count($sentences);
        $avgWordsPerSentence = $sentenceCount > 0 ? $words / $sentenceCount : 0;
        $avgSyllablesPerWord = $words > 0 ? $syllables / $words : 0;
        
        // Flesch Reading Ease Score (adapted for Vietnamese)
        $fleschScore = 206.835 - (1.015 * $avgWordsPerSentence) - (84.6 * $avgSyllablesPerWord);
        $fleschScore = max(0, min(100, $fleschScore));
        
        $readabilityLevel = $this->getReadabilityLevel($fleschScore);
        
        return [
            'flesch_score' => round($fleschScore, 2),
            'level' => $readabilityLevel,
            'avg_words_per_sentence' => round($avgWordsPerSentence, 2),
            'avg_syllables_per_word' => round($avgSyllablesPerWord, 2)
        ];
    }

    /**
     * Calculate SEO score
     */
    private function calculateSEOScore(string $content): array
    {
        $score = 0;
        $factors = [];
        
        // Content length
        $wordCount = str_word_count($content);
        if ($wordCount >= 300) {
            $score += 20;
            $factors[] = 'Adequate content length';
        } else {
            $factors[] = 'Content too short for SEO';
        }
        
        // Heading structure
        if (preg_match('/<h[1-6][^>]*>/', $content)) {
            $score += 15;
            $factors[] = 'Has heading structure';
        }
        
        // Internal structure
        if (preg_match_all('/<(p|div|span)[^>]*>/', $content) > 3) {
            $score += 10;
            $factors[] = 'Good paragraph structure';
        }
        
        // Keyword density (basic check)
        $keywordDensity = $this->calculateKeywordDensity($content);
        if ($keywordDensity > 0.5 && $keywordDensity < 3) {
            $score += 15;
            $factors[] = 'Good keyword density';
        }
        
        // Meta elements
        if (preg_match('/(title|description|keywords)/', $content)) {
            $score += 10;
            $factors[] = 'Contains meta elements';
        }
        
        // Links
        if (preg_match('/<a[^>]*href=/', $content)) {
            $score += 5;
            $factors[] = 'Contains links';
        }
        
        // Images with alt text
        if (preg_match('/<img[^>]*alt=/', $content)) {
            $score += 5;
            $factors[] = 'Images have alt text';
        }
        
        return [
            'score' => min(100, $score),
            'factors' => $factors,
            'keyword_density' => $keywordDensity
        ];
    }

    /**
     * Analyze sentiment using AI
     */
    private function analyzeSentiment(string $content): array
    {
        try {
            $provider = $this->factory->getBestProvider('sentiment_analysis');
            return $provider->analyzeContentSentiment($content);
        } catch (\Exception $e) {
            return [
                'sentiment_score' => 0,
                'sentiment_label' => 'unknown',
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Check content originality
     */
    private function checkOriginality(string $content): array
    {
        // Simple hash-based originality check
        // In production, this would integrate with plagiarism detection APIs
        
        $contentHash = md5($content);
        $wordCount = str_word_count($content);
        $uniqueness = min(100, ($wordCount / 10)); // Simplified calculation
        
        return [
            'uniqueness_score' => $uniqueness,
            'content_hash' => $contentHash,
            'is_original' => $uniqueness > 80
        ];
    }

    /**
     * Calculate overall quality score
     */
    private function calculateOverallScore(array $metrics): float
    {
        $weights = [
            'readability' => 0.25,
            'seo_score' => 0.25,
            'sentiment' => 0.20,
            'originality' => 0.30
        ];
        
        $score = 0;
        
        if (isset($metrics['readability']['flesch_score'])) {
            $score += $metrics['readability']['flesch_score'] * $weights['readability'];
        }
        
        if (isset($metrics['seo_score']['score'])) {
            $score += $metrics['seo_score']['score'] * $weights['seo_score'];
        }
        
        if (isset($metrics['sentiment']['sentiment_score'])) {
            $sentimentScore = ($metrics['sentiment']['sentiment_score'] + 1) * 50; // Convert -1 to 1 range to 0-100
            $score += $sentimentScore * $weights['sentiment'];
        }
        
        if (isset($metrics['originality']['uniqueness_score'])) {
            $score += $metrics['originality']['uniqueness_score'] * $weights['originality'];
        }
        
        return round($score, 2);
    }

    /**
     * Get quality grade
     */
    private function getQualityGrade(float $score): string
    {
        if ($score >= 90) return 'A+';
        if ($score >= 85) return 'A';
        if ($score >= 80) return 'A-';
        if ($score >= 75) return 'B+';
        if ($score >= 70) return 'B';
        if ($score >= 65) return 'B-';
        if ($score >= 60) return 'C+';
        if ($score >= 55) return 'C';
        if ($score >= 50) return 'C-';
        if ($score >= 45) return 'D+';
        if ($score >= 40) return 'D';
        return 'F';
    }

    /**
     * Generate improvement suggestions
     */
    private function generateImprovementSuggestions(string $content, array $metrics): array
    {
        $suggestions = [];
        
        // Readability suggestions
        if (isset($metrics['readability']['flesch_score']) && $metrics['readability']['flesch_score'] < 60) {
            $suggestions[] = 'Cải thiện độ dễ đọc bằng cách sử dụng câu ngắn hơn và từ đơn giản hơn';
        }
        
        // SEO suggestions
        if (isset($metrics['seo_score']['score']) && $metrics['seo_score']['score'] < 70) {
            $suggestions[] = 'Tối ưu SEO bằng cách thêm heading, cải thiện cấu trúc và tăng độ dài nội dung';
        }
        
        // Sentiment suggestions
        if (isset($metrics['sentiment']['sentiment_score']) && $metrics['sentiment']['sentiment_score'] < 0) {
            $suggestions[] = 'Cải thiện tone tích cực để tăng engagement';
        }
        
        // Length suggestions
        if ($metrics['word_count'] < 300) {
            $suggestions[] = 'Tăng độ dài nội dung để cải thiện SEO và cung cấp thêm giá trị';
        }
        
        // Originality suggestions
        if (isset($metrics['originality']['uniqueness_score']) && $metrics['originality']['uniqueness_score'] < 80) {
            $suggestions[] = 'Tăng tính độc đáo của nội dung';
        }
        
        return $suggestions;
    }

    /**
     * Build improvement prompt
     */
    private function buildImprovementPrompt(string $content, array $currentQuality, array $targetMetrics): string
    {
        $prompt = "Cải thiện nội dung sau để đạt được chất lượng cao hơn:\n\n";
        $prompt .= "NỘI DUNG HIỆN TẠI:\n{$content}\n\n";
        $prompt .= "ĐÁNH GIÁ CHẤT LƯỢNG HIỆN TẠI:\n";
        $prompt .= "- Điểm tổng thể: {$currentQuality['overall_score']}/100\n";
        $prompt .= "- Grade: {$currentQuality['grade']}\n\n";
        
        if (!empty($currentQuality['suggestions'])) {
            $prompt .= "CẦN CẢI THIỆN:\n";
            foreach ($currentQuality['suggestions'] as $suggestion) {
                $prompt .= "- {$suggestion}\n";
            }
        }
        
        return $prompt;
    }

    /**
     * Count syllables (simplified for Vietnamese)
     */
    private function countSyllables(string $text): int
    {
        // Simplified syllable counting for Vietnamese
        $words = str_word_count($text, 1);
        $syllableCount = 0;
        
        foreach ($words as $word) {
            // Basic Vietnamese syllable counting
            $syllableCount += max(1, preg_match_all('/[aeiouâêôơưáàảãạéèẻẽẹíìỉĩịóòỏõọúùủũụýỳỷỹỵ]/ui', $word));
        }
        
        return $syllableCount;
    }

    /**
     * Calculate keyword density
     */
    private function calculateKeywordDensity(string $content): float
    {
        $words = str_word_count($content);
        if ($words == 0) return 0;
        
        // Simple keyword density calculation
        // In production, this would analyze specific target keywords
        $commonWords = ['và', 'của', 'là', 'có', 'trong', 'với', 'để', 'được', 'một', 'các'];
        $keywordCount = 0;
        
        foreach ($commonWords as $keyword) {
            $keywordCount += substr_count(strtolower($content), $keyword);
        }
        
        return ($keywordCount / $words) * 100;
    }

    /**
     * Get readability level
     */
    private function getReadabilityLevel(float $score): string
    {
        if ($score >= 90) return 'Very Easy';
        if ($score >= 80) return 'Easy';
        if ($score >= 70) return 'Fairly Easy';
        if ($score >= 60) return 'Standard';
        if ($score >= 50) return 'Fairly Difficult';
        if ($score >= 30) return 'Difficult';
        return 'Very Difficult';
    }
}