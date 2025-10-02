<?php

namespace Services;

use AI\AIProviderInterface;
use AI\ContentRequest;
use Core\EventDispatcher;
use Core\Logger;
use Models\ContentPage;

/**
 * AI Content Service
 * Manages AI-powered content generation and optimization
 */
class AIContentService
{
    private AIProviderInterface $aiProvider;
    private ContentPage $contentModel;
    private EventDispatcher $events;
    private Logger $logger;
    private array $config;

    public function __construct(
        AIProviderInterface $aiProvider,
        ContentPage $contentModel,
        EventDispatcher $events,
        Logger $logger,
        array $config = []
    ) {
        $this->aiProvider = $aiProvider;
        $this->contentModel = $contentModel;
        $this->events = $events;
        $this->logger = $logger;
        $this->config = $config;
    }

    /**
     * Generate content from AI
     */
    public function generateContent(ContentRequest $request): array
    {
        $this->logger->info('Starting AI content generation', [
            'type' => $request->type,
            'topic' => $request->topic,
            'word_count' => $request->wordCount
        ]);

        try {
            // Build prompt
            $prompt = $this->buildContentPrompt($request);
            
            // Generate content
            $content = $this->aiProvider->generateContent($prompt, [
                'type' => $request->type,
                'word_count' => $request->wordCount,
                'tone' => $request->tone
            ]);

            // Generate SEO metadata
            $seoData = $this->aiProvider->generateSEOMetadata($content, $request->keywords[0] ?? '');

            // Create content draft
            $contentData = [
                'title' => $seoData['title'] ?: $this->generateTitle($request->topic),
                'content' => $content,
                'meta_title' => $seoData['title'],
                'meta_description' => $seoData['description'],
                'meta_keywords' => implode(', ', $seoData['keywords']),
                'target_keywords' => implode(', ', $request->keywords),
                'content_type' => $request->type,
                'status' => $this->config['auto_publish'] ?? false ? 'published' : 'draft',
                'ai_generated' => 1,
                'author_id' => $_SESSION['uid'] ?? 1,
                'created_at' => date('Y-m-d H:i:s')
            ];

            // Calculate SEO score
            $contentData['seo_score'] = $this->calculateSEOScore($contentData);

            // Save content
            $contentId = $this->contentModel->create($contentData);

            // Dispatch event
            $this->events->dispatch('content.generated', [
                'content_id' => $contentId,
                'request' => $request,
                'ai_provider' => get_class($this->aiProvider)
            ]);

            $this->logger->info('AI content generated successfully', [
                'content_id' => $contentId,
                'type' => $request->type
            ]);

            return [
                'id' => $contentId,
                'title' => $contentData['title'],
                'content' => $content,
                'seo_data' => $seoData,
                'seo_score' => $contentData['seo_score']
            ];

        } catch (\Exception $e) {
            $this->logger->error('AI content generation failed', [
                'error' => $e->getMessage(),
                'request' => $request
            ]);
            throw $e;
        }
    }

    /**
     * Improve existing content with AI
     */
    public function improveContent(int $contentId): array
    {
        $content = $this->contentModel->find($contentId);
        if (!$content) {
            throw new \Exception('Content not found');
        }

        try {
            $improvedContent = $this->aiProvider->improveContent(
                $content['content'],
                $content['content_type']
            );

            $seoData = $this->aiProvider->generateSEOMetadata(
                $improvedContent,
                $content['target_keywords']
            );

            $updateData = [
                'content' => $improvedContent,
                'meta_title' => $seoData['title'],
                'meta_description' => $seoData['description'],
                'seo_score' => $this->calculateSEOScore(array_merge($content, [
                    'content' => $improvedContent,
                    'meta_title' => $seoData['title'],
                    'meta_description' => $seoData['description']
                ])),
                'updated_at' => date('Y-m-d H:i:s')
            ];

            $this->contentModel->update($contentId, $updateData);

            $this->events->dispatch('content.improved', [
                'content_id' => $contentId,
                'original_score' => $content['seo_score'],
                'new_score' => $updateData['seo_score']
            ]);

            return [
                'original_content' => $content['content'],
                'improved_content' => $improvedContent,
                'seo_improvement' => $updateData['seo_score'] - $content['seo_score'],
                'seo_data' => $seoData
            ];

        } catch (\Exception $e) {
            $this->logger->error('Content improvement failed', [
                'content_id' => $contentId,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Schedule content generation
     */
    public function scheduleContentGeneration(array $schedule): void
    {
        // This would typically use a queue system
        $this->events->dispatch('content.schedule_created', $schedule);
    }

    /**
     * Build content generation prompt
     */
    private function buildContentPrompt(ContentRequest $request): string
    {
        $prompt = "Tạo {$request->type} về chủ đề: {$request->topic}\n\n";
        
        if (!empty($request->keywords)) {
            $prompt .= "Từ khóa chính: " . implode(', ', $request->keywords) . "\n";
        }
        
        $prompt .= "Độ dài: {$request->wordCount} từ\n";
        $prompt .= "Tone: {$request->tone}\n";
        $prompt .= "Ngôn ngữ: {$request->language}\n\n";
        
        if (!empty($request->requirements)) {
            $prompt .= "Yêu cầu đặc biệt:\n";
            foreach ($request->requirements as $requirement) {
                $prompt .= "- {$requirement}\n";
            }
        }
        
        $prompt .= "\nTạo nội dung chất lượng cao, SEO-friendly, engaging và có giá trị cho người đọc.";
        
        return $prompt;
    }

    /**
     * Generate title from topic
     */
    private function generateTitle(string $topic): string
    {
        return ucfirst($topic);
    }

    /**
     * Calculate SEO score
     */
    private function calculateSEOScore(array $content): int
    {
        $score = 0;
        
        // Title length (ideal: 50-60 chars)
        $titleLength = strlen($content['meta_title'] ?? $content['title']);
        if ($titleLength >= 50 && $titleLength <= 60) {
            $score += 20;
        } elseif ($titleLength >= 30 && $titleLength <= 70) {
            $score += 15;
        } else {
            $score += 5;
        }
        
        // Description length (ideal: 150-160 chars)
        $descLength = strlen($content['meta_description'] ?? '');
        if ($descLength >= 150 && $descLength <= 160) {
            $score += 20;
        } elseif ($descLength >= 120 && $descLength <= 180) {
            $score += 15;
        } else {
            $score += 5;
        }
        
        // Content length
        $contentLength = str_word_count($content['content'] ?? '');
        if ($contentLength >= 300) {
            $score += 20;
        } elseif ($contentLength >= 150) {
            $score += 10;
        }
        
        // Keywords presence
        if (!empty($content['target_keywords'])) {
            $score += 20;
        }
        
        // Meta keywords
        if (!empty($content['meta_keywords'])) {
            $score += 20;
        }
        
        return min($score, 100);
    }
}