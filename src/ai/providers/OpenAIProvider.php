<?php

namespace AI\Providers;

use AI\AIProviderInterface;
use Core\Logger;

/**
 * OpenAI GPT Provider
 * Integrates with OpenAI API for content generation
 */
class OpenAIProvider implements AIProviderInterface
{
    private string $apiKey;
    private string $model;
    private int $maxTokens;
    private Logger $logger;

    public function __construct(string $apiKey, string $model = 'gpt-4', int $maxTokens = 2000, Logger $logger = null)
    {
        $this->apiKey = $apiKey;
        $this->model = $model;
        $this->maxTokens = $maxTokens;
        $this->logger = $logger ?: new Logger();
    }

    public function generateContent(string $prompt, array $options = []): string
    {
        $systemPrompt = $this->buildSystemPrompt($options);
        
        $data = [
            'model' => $this->model,
            'messages' => [
                ['role' => 'system', 'content' => $systemPrompt],
                ['role' => 'user', 'content' => $prompt]
            ],
            'max_tokens' => $options['max_tokens'] ?? $this->maxTokens,
            'temperature' => $options['temperature'] ?? 0.7,
        ];

        try {
            $response = $this->makeRequest('chat/completions', $data);
            $content = $response['choices'][0]['message']['content'] ?? '';
            
            $this->logger->info('AI Content Generated', [
                'provider' => 'openai',
                'model' => $this->model,
                'prompt_length' => strlen($prompt),
                'response_length' => strlen($content)
            ]);
            
            return $content;
        } catch (\Exception $e) {
            $this->logger->error('AI Content Generation Failed', [
                'provider' => 'openai',
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    public function improveContent(string $content, string $type = 'blog'): string
    {
        $prompt = "Cải thiện nội dung {$type} sau đây để tối ưu SEO, tăng engagement và chất lượng writing:\n\n{$content}";
        
        return $this->generateContent($prompt, [
            'type' => 'content_improvement',
            'content_type' => $type
        ]);
    }

    public function generateSocialMediaPost(string $content, string $platform = 'facebook'): string
    {
        $platformSpecs = $this->getSocialMediaSpecs($platform);
        $prompt = "Tạo post {$platform} từ nội dung sau. {$platformSpecs['instructions']}:\n\n{$content}";
        
        return $this->generateContent($prompt, [
            'type' => 'social_media',
            'platform' => $platform,
            'max_tokens' => $platformSpecs['max_tokens']
        ]);
    }

    public function generateSEOMetadata(string $content, string $targetKeyword = ''): array
    {
        $prompt = "Tạo SEO metadata (title, description, keywords) cho nội dung sau. Target keyword: '{$targetKeyword}':\n\n{$content}";
        
        $response = $this->generateContent($prompt, [
            'type' => 'seo_metadata',
            'target_keyword' => $targetKeyword
        ]);

        return $this->parseSEOMetadata($response);
    }

    public function analyzeContentSentiment(string $content): array
    {
        $prompt = "Phân tích sentiment và emotion của nội dung sau, trả về JSON với score từ -1 đến 1 và các emotions:\n\n{$content}";
        
        $response = $this->generateContent($prompt, [
            'type' => 'sentiment_analysis'
        ]);

        try {
            return json_decode($response, true) ?: ['score' => 0, 'emotions' => []];
        } catch (\Exception $e) {
            return ['score' => 0, 'emotions' => []];
        }
    }

    private function buildSystemPrompt(array $options): string
    {
        $basePrompt = "Bạn là một AI assistant chuyên về digital marketing và content creation.";
        
        switch ($options['type'] ?? 'general') {
            case 'blog':
                return $basePrompt . " Tạo nội dung blog chất lượng cao, SEO-friendly, hấp dẫn người đọc.";
            case 'social_media':
                return $basePrompt . " Tạo nội dung social media viral, engaging, phù hợp với platform.";
            case 'seo_metadata':
                return $basePrompt . " Tạo SEO metadata tối ưu cho search engines và user experience.";
            case 'content_improvement':
                return $basePrompt . " Cải thiện nội dung để tăng chất lượng, SEO và engagement.";
            default:
                return $basePrompt;
        }
    }

    private function getSocialMediaSpecs(string $platform): array
    {
        $specs = [
            'facebook' => [
                'max_tokens' => 300,
                'instructions' => 'Tạo post Facebook hấp dẫn với call-to-action, emojis phù hợp, max 280 characters'
            ],
            'instagram' => [
                'max_tokens' => 400,
                'instructions' => 'Tạo Instagram caption với hashtags trending, storytelling, max 2200 characters'
            ],
            'twitter' => [
                'max_tokens' => 150,
                'instructions' => 'Tạo tweet ngắn gọn, viral, max 280 characters với hashtags phù hợp'
            ],
            'linkedin' => [
                'max_tokens' => 500,
                'instructions' => 'Tạo LinkedIn post professional, thought leadership, business insights'
            ]
        ];

        return $specs[$platform] ?? $specs['facebook'];
    }

    private function parseSEOMetadata(string $response): array
    {
        // Simple parsing - could be improved with structured prompts
        $lines = explode("\n", $response);
        $metadata = ['title' => '', 'description' => '', 'keywords' => []];
        
        foreach ($lines as $line) {
            if (stripos($line, 'title:') !== false) {
                $metadata['title'] = trim(str_ireplace('title:', '', $line));
            } elseif (stripos($line, 'description:') !== false) {
                $metadata['description'] = trim(str_ireplace('description:', '', $line));
            } elseif (stripos($line, 'keywords:') !== false) {
                $keywords = trim(str_ireplace('keywords:', '', $line));
                $metadata['keywords'] = array_map('trim', explode(',', $keywords));
            }
        }
        
        return $metadata;
    }

    private function makeRequest(string $endpoint, array $data): array
    {
        $url = "https://api.openai.com/v1/{$endpoint}";
        
        $options = [
            'http' => [
                'method' => 'POST',
                'header' => [
                    'Authorization: Bearer ' . $this->apiKey,
                    'Content-Type: application/json',
                ],
                'content' => json_encode($data),
            ],
        ];
        
        $context = stream_context_create($options);
        $response = file_get_contents($url, false, $context);
        
        if ($response === false) {
            throw new \Exception('Failed to make OpenAI API request');
        }
        
        $decoded = json_decode($response, true);
        
        if (isset($decoded['error'])) {
            throw new \Exception('OpenAI API Error: ' . $decoded['error']['message']);
        }
        
        return $decoded;
    }
}