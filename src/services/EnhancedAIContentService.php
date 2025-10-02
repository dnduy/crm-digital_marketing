<?php

namespace Services;

use AI\AIProviderFactory;
use AI\AIProviderManager;
use AI\AIConfig;
use AI\AIQualityManager;
use AI\ContentRequest;
use AI\SocialMediaRequest;
use Core\EventDispatcher;
use Core\Logger;
use Repositories\ContentRepository;
use Repositories\SocialMediaPostRepository;

/**
 * Enhanced AI Content Service
 * Complete AI-powered content generation and management system
 */
class EnhancedAIContentService
{
    private AIProviderManager $aiManager;
    private AIConfig $config;
    private AIQualityManager $qualityManager;
    private ContentRepository $contentRepo;
    private SocialMediaPostRepository $socialRepo;
    private EventDispatcher $events;
    private Logger $logger;
    private array $cache = [];

    public function __construct(
        AIProviderManager $aiManager,
        AIConfig $config,
        AIQualityManager $qualityManager,
        ContentRepository $contentRepo,
        SocialMediaPostRepository $socialRepo,
        EventDispatcher $events,
        Logger $logger
    ) {
        $this->aiManager = $aiManager;
        $this->config = $config;
        $this->qualityManager = $qualityManager;
        $this->contentRepo = $contentRepo;
        $this->socialRepo = $socialRepo;
        $this->events = $events;
        $this->logger = $logger;
    }

    /**
     * Generate comprehensive content with AI
     */
    public function generateComprehensiveContent(ContentRequest $request): array
    {
        $this->logger->info('Starting comprehensive AI content generation', [
            'type' => $request->type,
            'topic' => $request->topic,
            'word_count' => $request->wordCount
        ]);

        try {
            // 1. Generate main content
            $mainContent = $this->generateMainContent($request);
            
            // 2. Generate SEO metadata
            $seoMetadata = $this->generateSEOMetadata($mainContent, $request->keywords);
            
            // 3. Generate social media variants
            $socialMediaPosts = $this->generateSocialMediaVariants($mainContent, $request);
            
            // 4. Analyze content quality
            $qualityMetrics = $this->qualityManager->assessQuality($mainContent);
            
            // 5. Improve content if needed
            if ($qualityMetrics['overall_score'] < 80) {
                $improved = $this->qualityManager->improveContent($mainContent);
                if ($improved['success']) {
                    $mainContent = $improved['improved_content'];
                    $qualityMetrics = $improved['improved_quality'];
                }
            }
            
            // 6. Save to database
            $contentId = $this->saveContent($mainContent, $seoMetadata, $request);
            $this->saveSocialMediaPosts($socialMediaPosts, $contentId);
            
            // 7. Dispatch events
            $this->events->dispatch('ai.content.generated', [
                'content_id' => $contentId,
                'quality_score' => $qualityMetrics['overall_score'],
                'social_posts_count' => count($socialMediaPosts)
            ]);
            
            return [
                'success' => true,
                'content_id' => $contentId,
                'main_content' => $mainContent,
                'seo_metadata' => $seoMetadata,
                'social_media_posts' => $socialMediaPosts,
                'quality_metrics' => $qualityMetrics,
                'word_count' => str_word_count($mainContent),
                'generation_time' => microtime(true) - $request->startTime ?? 0
            ];
            
        } catch (\Exception $e) {
            $this->logger->error('Comprehensive content generation failed', [
                'error' => $e->getMessage(),
                'request' => $request
            ]);
            
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Generate content with multiple AI providers for comparison
     */
    public function generateContentComparison(ContentRequest $request): array
    {
        $results = [];
        $providers = $this->aiManager->factory->getAvailableProviders();
        
        foreach ($providers as $providerName) {
            try {
                $provider = $this->aiManager->factory->getProvider($providerName);
                $prompt = $this->buildContentPrompt($request);
                
                $startTime = microtime(true);
                $content = $provider->generateContent($prompt, [
                    'task_type' => 'content_generation',
                    'max_tokens' => $request->wordCount * 1.5
                ]);
                $generationTime = microtime(true) - $startTime;
                
                $quality = $this->qualityManager->assessQuality($content);
                
                $results[$providerName] = [
                    'content' => $content,
                    'quality_metrics' => $quality,
                    'generation_time' => round($generationTime * 1000, 2) . 'ms',
                    'word_count' => str_word_count($content),
                    'character_count' => strlen($content)
                ];
                
            } catch (\Exception $e) {
                $results[$providerName] = [
                    'error' => $e->getMessage(),
                    'success' => false
                ];
            }
        }
        
        // Rank results by quality
        uasort($results, function($a, $b) {
            $scoreA = $a['quality_metrics']['overall_score'] ?? 0;
            $scoreB = $b['quality_metrics']['overall_score'] ?? 0;
            return $scoreB <=> $scoreA;
        });
        
        return [
            'comparison_results' => $results,
            'best_provider' => array_key_first($results),
            'total_providers_tested' => count($providers),
            'successful_generations' => count(array_filter($results, fn($r) => !isset($r['error'])))
        ];
    }

    /**
     * Generate content in multiple languages
     */
    public function generateMultilingualContent(ContentRequest $request, array $languages): array
    {
        $results = [];
        $basePrompt = $this->buildContentPrompt($request);
        
        foreach ($languages as $language) {
            try {
                $localizedPrompt = $basePrompt . "\n\nVui lòng tạo nội dung bằng tiếng {$language}.";
                
                $content = $this->aiManager->generateContent($localizedPrompt, [
                    'task_type' => 'multilingual_content',
                    'language' => $language,
                    'load_balancing' => true
                ]);
                
                if ($content['success']) {
                    $quality = $this->qualityManager->assessQuality($content['content']);
                    
                    $results[$language] = [
                        'content' => $content['content'],
                        'quality_metrics' => $quality,
                        'provider_used' => $content['provider'],
                        'processing_time' => $content['processing_time']
                    ];
                }
                
            } catch (\Exception $e) {
                $results[$language] = [
                    'error' => $e->getMessage(),
                    'success' => false
                ];
            }
        }
        
        return $results;
    }

    /**
     * Generate content series/campaign
     */
    public function generateContentSeries(array $topics, ContentRequest $baseRequest, int $seriesLength = 5): array
    {
        $series = [];
        $overallTheme = $baseRequest->topic;
        
        for ($i = 0; $i < $seriesLength; $i++) {
            $seriesRequest = clone $baseRequest;
            $seriesRequest->topic = $topics[$i] ?? "{$overallTheme} - Phần " . ($i + 1);
            
            // Add series context
            $seriesRequest->requirements[] = "Đây là phần " . ($i + 1) . " trong series {$seriesLength} bài về {$overallTheme}";
            if ($i > 0) {
                $seriesRequest->requirements[] = "Liên kết với các bài trước trong series";
            }
            if ($i < $seriesLength - 1) {
                $seriesRequest->requirements[] = "Tạo anticipation cho bài tiếp theo";
            }
            
            $result = $this->generateComprehensiveContent($seriesRequest);
            
            if ($result['success']) {
                $series[] = [
                    'part_number' => $i + 1,
                    'title' => $seriesRequest->topic,
                    'content_id' => $result['content_id'],
                    'quality_score' => $result['quality_metrics']['overall_score'],
                    'word_count' => $result['word_count']
                ];
            }
        }
        
        return [
            'series_title' => $overallTheme,
            'total_parts' => $seriesLength,
            'completed_parts' => count($series),
            'average_quality' => count($series) > 0 ? array_sum(array_column($series, 'quality_score')) / count($series) : 0,
            'parts' => $series
        ];
    }

    /**
     * Optimize existing content with AI
     */
    public function optimizeExistingContent(int $contentId, array $optimizationGoals = []): array
    {
        try {
            $content = $this->contentRepo->find($contentId);
            if (!$content) {
                throw new \Exception("Content not found: {$contentId}");
            }
            
            $originalText = $content['content'] ?? $content['body'] ?? '';
            $currentQuality = $this->qualityManager->assessQuality($originalText);
            
            // Build optimization prompt based on goals
            $optimizationPrompt = $this->buildOptimizationPrompt($originalText, $optimizationGoals, $currentQuality);
            
            $result = $this->aiManager->generateContent($optimizationPrompt, [
                'task_type' => 'content_improvement',
                'temperature' => 0.5 // Lower temperature for optimization
            ]);
            
            if ($result['success']) {
                $optimizedContent = $result['content'];
                $newQuality = $this->qualityManager->assessQuality($optimizedContent);
                
                // Update content in database
                $this->contentRepo->update($contentId, [
                    'content' => $optimizedContent,
                    'ai_optimized' => 1,
                    'optimization_date' => date('Y-m-d H:i:s'),
                    'quality_score' => $newQuality['overall_score']
                ]);
                
                return [
                    'success' => true,
                    'content_id' => $contentId,
                    'original_content' => $originalText,
                    'optimized_content' => $optimizedContent,
                    'original_quality' => $currentQuality,
                    'optimized_quality' => $newQuality,
                    'improvement_score' => $newQuality['overall_score'] - $currentQuality['overall_score'],
                    'optimizations_applied' => $optimizationGoals
                ];
            }
            
            throw new \Exception("Failed to optimize content: " . ($result['error'] ?? 'Unknown error'));
            
        } catch (\Exception $e) {
            $this->logger->error('Content optimization failed', [
                'content_id' => $contentId,
                'error' => $e->getMessage()
            ]);
            
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Generate AI content report
     */
    public function generateContentReport(array $filters = []): array
    {
        // Get AI-generated content statistics
        $aiContent = $this->contentRepo->getAIGeneratedContent($filters);
        $totalContent = $this->contentRepo->getTotalContentCount($filters);
        
        // Calculate AI adoption metrics
        $aiAdoptionRate = $totalContent > 0 ? (count($aiContent) / $totalContent) * 100 : 0;
        
        // Quality analysis
        $qualityStats = $this->calculateQualityStatistics($aiContent);
        
        // Provider usage stats
        $providerStats = $this->aiManager->getUsageStats();
        
        // Cost analysis
        $costAnalysis = $this->calculateCostAnalysis($providerStats);
        
        // Performance metrics
        $performanceMetrics = $this->calculatePerformanceMetrics($aiContent);
        
        return [
            'summary' => [
                'total_ai_content' => count($aiContent),
                'total_content' => $totalContent,
                'ai_adoption_rate' => round($aiAdoptionRate, 2),
                'average_quality_score' => $qualityStats['average_score'],
                'total_cost_estimate' => $costAnalysis['total_cost_vnd']
            ],
            'quality_analysis' => $qualityStats,
            'provider_usage' => $providerStats,
            'cost_analysis' => $costAnalysis,
            'performance_metrics' => $performanceMetrics,
            'recommendations' => $this->generateRecommendations($aiContent, $providerStats)
        ];
    }

    /**
     * Build content prompt
     */
    private function buildContentPrompt(ContentRequest $request): string
    {
        $prompt = "Tạo {$request->type} chất lượng cao về chủ đề: {$request->topic}\n\n";
        
        $prompt .= "YÊU CẦU:\n";
        $prompt .= "- Độ dài: khoảng {$request->wordCount} từ\n";
        $prompt .= "- Tone: {$request->tone}\n";
        $prompt .= "- Ngôn ngữ: {$request->language}\n";
        
        if (!empty($request->keywords)) {
            $prompt .= "- Từ khóa: " . implode(', ', $request->keywords) . "\n";
        }
        
        if (!empty($request->requirements)) {
            $prompt .= "- Yêu cầu đặc biệt: " . implode(', ', $request->requirements) . "\n";
        }
        
        $prompt .= "\nTạo nội dung SEO-friendly, engaging và có giá trị thực tế cho người đọc.";
        
        return $prompt;
    }

    /**
     * Generate main content
     */
    private function generateMainContent(ContentRequest $request): string
    {
        $prompt = $this->buildContentPrompt($request);
        
        $result = $this->aiManager->generateContent($prompt, [
            'task_type' => 'content_generation',
            'load_balancing' => true,
            'max_tokens' => $request->wordCount * 1.5,
            'temperature' => 0.7
        ]);
        
        if (!$result['success']) {
            throw new \Exception("Failed to generate main content: " . $result['error']);
        }
        
        return $result['content'];
    }

    /**
     * Generate SEO metadata
     */
    private function generateSEOMetadata(string $content, array $keywords): array
    {
        $targetKeyword = !empty($keywords) ? $keywords[0] : '';
        
        $result = $this->aiManager->generateContent("Generate SEO metadata for content", [
            'task_type' => 'seo_optimization',
            'target_keyword' => $targetKeyword,
            'content' => $content
        ]);
        
        if ($result['success']) {
            // Parse SEO metadata from result
            $provider = $this->aiManager->factory->getBestProvider('seo_optimization');
            return $provider->generateSEOMetadata($content, $targetKeyword);
        }
        
        return [
            'title' => 'AI Generated Title',
            'description' => 'AI Generated Description',
            'keywords' => $keywords
        ];
    }

    /**
     * Generate social media variants
     */
    private function generateSocialMediaVariants(string $content, ContentRequest $request): array
    {
        $platforms = ['facebook', 'instagram', 'twitter', 'linkedin'];
        $socialPosts = [];
        
        foreach ($platforms as $platform) {
            try {
                $provider = $this->aiManager->factory->getBestProvider('social_media');
                $post = $provider->generateSocialMediaPost($content, $platform);
                
                $socialPosts[$platform] = [
                    'platform' => $platform,
                    'content' => $post,
                    'character_count' => strlen($post),
                    'status' => 'draft'
                ];
                
            } catch (\Exception $e) {
                $socialPosts[$platform] = [
                    'platform' => $platform,
                    'error' => $e->getMessage(),
                    'status' => 'failed'
                ];
            }
        }
        
        return $socialPosts;
    }

    /**
     * Save content to database
     */
    private function saveContent(string $content, array $seoMetadata, ContentRequest $request): int
    {
        $data = [
            'title' => $seoMetadata['title'] ?? $request->topic,
            'content' => $content,
            'type' => $request->type,
            'status' => 'draft',
            'ai_generated' => 1,
            'ai_provider' => 'multi-provider',
            'keywords' => implode(',', $request->keywords),
            'meta_description' => $seoMetadata['description'] ?? '',
            'word_count' => str_word_count($content),
            'language' => $request->language,
            'tone' => $request->tone
        ];
        
        return $this->contentRepo->create($data);
    }

    /**
     * Save social media posts
     */
    private function saveSocialMediaPosts(array $socialPosts, int $contentId): void
    {
        foreach ($socialPosts as $post) {
            if (isset($post['content']) && !isset($post['error'])) {
                $this->socialRepo->create([
                    'content_id' => $contentId,
                    'platform' => $post['platform'],
                    'content' => $post['content'],
                    'status' => 'draft',
                    'ai_generated' => 1,
                    'character_count' => $post['character_count']
                ]);
            }
        }
    }

    /**
     * Build optimization prompt
     */
    private function buildOptimizationPrompt(string $content, array $goals, array $currentQuality): string
    {
        $prompt = "Tối ưu nội dung sau để cải thiện chất lượng:\n\n";
        $prompt .= "NỘI DUNG HIỆN TẠI:\n{$content}\n\n";
        
        $prompt .= "ĐÁNH GIÁ HIỆN TẠI:\n";
        $prompt .= "- Điểm chất lượng: {$currentQuality['overall_score']}/100\n";
        $prompt .= "- Grade: {$currentQuality['grade']}\n\n";
        
        if (!empty($goals)) {
            $prompt .= "MỤC TIÊU TỐI ƯU:\n";
            foreach ($goals as $goal) {
                $prompt .= "- {$goal}\n";
            }
            $prompt .= "\n";
        }
        
        if (!empty($currentQuality['suggestions'])) {
            $prompt .= "CẦN CẢI THIỆN:\n";
            foreach ($currentQuality['suggestions'] as $suggestion) {
                $prompt .= "- {$suggestion}\n";
            }
        }
        
        return $prompt;
    }

    /**
     * Calculate quality statistics
     */
    private function calculateQualityStatistics(array $content): array
    {
        if (empty($content)) {
            return ['average_score' => 0, 'grade_distribution' => []];
        }
        
        $scores = array_filter(array_column($content, 'quality_score'));
        $averageScore = array_sum($scores) / count($scores);
        
        $gradeDistribution = [];
        foreach ($scores as $score) {
            $grade = $this->qualityManager->getQualityGrade($score);
            $gradeDistribution[$grade] = ($gradeDistribution[$grade] ?? 0) + 1;
        }
        
        return [
            'average_score' => round($averageScore, 2),
            'highest_score' => max($scores),
            'lowest_score' => min($scores),
            'grade_distribution' => $gradeDistribution,
            'total_analyzed' => count($scores)
        ];
    }

    /**
     * Calculate cost analysis
     */
    private function calculateCostAnalysis(array $providerStats): array
    {
        $totalCostUSD = 0;
        $costBreakdown = [];
        
        foreach ($providerStats['cost_estimates'] ?? [] as $provider => $estimate) {
            $costBreakdown[$provider] = $estimate;
            $totalCostUSD += $estimate['estimated_cost_usd'];
        }
        
        return [
            'total_cost_usd' => round($totalCostUSD, 4),
            'total_cost_vnd' => round($totalCostUSD * 24000, 0),
            'cost_breakdown' => $costBreakdown,
            'average_cost_per_request' => $providerStats['total_requests'] > 0 
                ? round($totalCostUSD / $providerStats['total_requests'], 4) : 0
        ];
    }

    /**
     * Calculate performance metrics
     */
    private function calculatePerformanceMetrics(array $content): array
    {
        $totalWords = array_sum(array_column($content, 'word_count'));
        $avgWordsPerContent = count($content) > 0 ? $totalWords / count($content) : 0;
        
        return [
            'total_words_generated' => $totalWords,
            'average_words_per_content' => round($avgWordsPerContent, 0),
            'total_content_pieces' => count($content),
            'content_types' => array_count_values(array_column($content, 'type'))
        ];
    }

    /**
     * Generate recommendations
     */
    private function generateRecommendations(array $content, array $providerStats): array
    {
        $recommendations = [];
        
        // Quality recommendations
        $avgQuality = $this->calculateQualityStatistics($content)['average_score'];
        if ($avgQuality < 80) {
            $recommendations[] = "Cải thiện chất lượng content bằng cách sử dụng quality manager";
        }
        
        // Provider recommendations
        $mostUsed = $providerStats['most_used_provider'] ?? '';
        if ($mostUsed) {
            $recommendations[] = "Provider được sử dụng nhiều nhất: {$mostUsed}. Cân nhắc load balancing";
        }
        
        // Cost optimization
        $totalCost = $this->calculateCostAnalysis($providerStats)['total_cost_vnd'];
        if ($totalCost > 1000000) { // > 1M VND
            $recommendations[] = "Chi phí AI cao. Cân nhắc sử dụng provider cost-effective hơn";
        }
        
        return $recommendations;
    }
}