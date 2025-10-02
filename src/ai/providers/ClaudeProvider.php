<?php

namespace AI\Providers;

use AI\AIProviderInterface;
use Core\Logger;

/**
 * Claude (Anthropic) AI Provider
 * Integrates with Anthropic's Claude API for content generation
 */
class ClaudeProvider implements AIProviderInterface
{
    private string $apiKey;
    private string $model;
    private int $maxTokens;
    private Logger $logger;
    private string $baseUrl = 'https://api.anthropic.com/v1';

    public function __construct(string $apiKey, string $model = 'claude-3-sonnet-20240229', int $maxTokens = 4000, Logger $logger = null)
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
            'max_tokens' => $options['max_tokens'] ?? $this->maxTokens,
            'temperature' => $options['temperature'] ?? 0.7,
            'system' => $systemPrompt,
            'messages' => [
                ['role' => 'user', 'content' => $prompt]
            ]
        ];

        try {
            $response = $this->makeRequest('messages', $data);
            $content = $response['content'][0]['text'] ?? '';
            
            $this->logger->info('AI Content Generated', [
                'provider' => 'claude',
                'model' => $this->model,
                'prompt_length' => strlen($prompt),
                'response_length' => strlen($content)
            ]);
            
            return $content;
        } catch (\Exception $e) {
            $this->logger->error('AI Content Generation Failed', [
                'provider' => 'claude',
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    public function improveContent(string $content, string $type = 'blog'): string
    {
        $prompt = "Hãy cải thiện nội dung {$type} sau đây để tối ưu SEO, tăng engagement và chất lượng viết:

NỘI DUNG GỐC:
{$content}

YÊU CẦU CẢI THIỆN:
- Tối ưu SEO với từ khóa tự nhiên
- Cải thiện cấu trúc và flow
- Tăng tính hấp dẫn và engagement
- Giữ nguyên tone và style gốc
- Bổ sung call-to-action phù hợp";
        
        return $this->generateContent($prompt, [
            'type' => 'content_improvement',
            'content_type' => $type,
            'temperature' => 0.5  // Lower temperature for improvement tasks
        ]);
    }

    public function generateSocialMediaPost(string $content, string $platform = 'facebook'): string
    {
        $platformSpecs = $this->getSocialMediaSpecs($platform);
        $prompt = "Tạo một post {$platform} hấp dẫn từ nội dung sau. {$platformSpecs['instructions']}:

NỘI DUNG GỐC:
{$content}

YÊU CẦU:
- Tối đa {$platformSpecs['character_limit']} ký tự
- Phù hợp với đặc thù của {$platform}
- Có emoji và hashtag phù hợp
- Tạo hook thu hút người đọc
- Có call-to-action rõ ràng";
        
        return $this->generateContent($prompt, [
            'type' => 'social_media',
            'platform' => $platform,
            'max_tokens' => $platformSpecs['max_tokens'],
            'temperature' => 0.8  // Higher creativity for social media
        ]);
    }

    public function generateSEOMetadata(string $content, string $targetKeyword = ''): array
    {
        $prompt = "Tạo SEO metadata hoàn chỉnh cho nội dung sau. Trả về định dạng JSON chuẩn.

TARGET KEYWORD: {$targetKeyword}

NỘI DUNG:
{$content}

YÊU CẦU METADATA:
- Title: 50-60 ký tự, có target keyword
- Description: 150-160 ký tự, hấp dẫn
- Keywords: 5-10 từ khóa liên quan
- Open Graph title và description
- Schema markup suggestions

Trả về JSON format:
{
  \"title\": \"...\",
  \"description\": \"...\",
  \"keywords\": [...],
  \"og_title\": \"...\",
  \"og_description\": \"...\",
  \"schema_suggestions\": [...]
}";
        
        $response = $this->generateContent($prompt, [
            'type' => 'seo_metadata',
            'target_keyword' => $targetKeyword,
            'temperature' => 0.3  // Low temperature for structured output
        ]);

        return $this->parseSEOMetadata($response);
    }

    public function analyzeContentSentiment(string $content): array
    {
        $prompt = "Phân tích sentiment và emotion của nội dung sau. Trả về JSON với thông tin chi tiết.

NỘI DUNG:
{$content}

YÊU CẦU PHÂN TÍCH:
- Overall sentiment score (-1 to 1)
- Emotion categories với scores
- Key phrases ảnh hưởng sentiment
- Suggestions để cải thiện tone nếu cần

Trả về JSON format:
{
  \"sentiment_score\": 0.5,
  \"sentiment_label\": \"positive\",
  \"emotions\": {
    \"joy\": 0.3,
    \"trust\": 0.4,
    \"excitement\": 0.2
  },
  \"key_phrases\": [...],
  \"suggestions\": [...]
}";
        
        $response = $this->generateContent($prompt, [
            'type' => 'sentiment_analysis',
            'temperature' => 0.1  // Very low temperature for analysis
        ]);

        return $this->parseSentimentAnalysis($response);
    }

    /**
     * Build system prompt based on options
     */
    private function buildSystemPrompt(array $options): string
    {
        $basePrompt = "Bạn là một AI content creator chuyên nghiệp, chuyên tạo nội dung marketing tiếng Việt chất lượng cao.";
        
        $type = $options['type'] ?? 'general';
        
        switch ($type) {
            case 'content_improvement':
                return $basePrompt . " Nhiệm vụ của bạn là cải thiện nội dung existing để tối ưu SEO và tăng engagement.";
                
            case 'social_media':
                $platform = $options['platform'] ?? 'facebook';
                return $basePrompt . " Chuyên tạo content cho {$platform}, hiểu rõ đặc thù từng platform và cách tối ưu engagement.";
                
            case 'seo_metadata':
                return $basePrompt . " Chuyên gia SEO, tạo metadata tối ưu cho search engines và social media.";
                
            case 'sentiment_analysis':
                return $basePrompt . " Chuyên gia phân tích sentiment và emotion trong content marketing.";
                
            default:
                return $basePrompt . " Tạo nội dung marketing đa dạng với chất lượng cao và tối ưu conversion.";
        }
    }

    /**
     * Get social media platform specifications
     */
    private function getSocialMediaSpecs(string $platform): array
    {
        $specs = [
            'facebook' => [
                'character_limit' => 2000,
                'max_tokens' => 500,
                'instructions' => 'Facebook post với engagement cao, có thể dài, focus vào storytelling và community building'
            ],
            'instagram' => [
                'character_limit' => 2200,
                'max_tokens' => 400,
                'instructions' => 'Instagram post visual-first, hashtag strategy, ngắn gọn hấp dẫn'
            ],
            'twitter' => [
                'character_limit' => 280,
                'max_tokens' => 100,
                'instructions' => 'Twitter post ngắn gọn, viral potential, trending hashtags'
            ],
            'linkedin' => [
                'character_limit' => 1300,
                'max_tokens' => 300,
                'instructions' => 'LinkedIn post professional, thought leadership, business insights'
            ],
            'tiktok' => [
                'character_limit' => 150,
                'max_tokens' => 80,
                'instructions' => 'TikTok caption ngắn, trend-aware, call-to-action xem video'
            ]
        ];

        return $specs[$platform] ?? $specs['facebook'];
    }

    /**
     * Parse SEO metadata response
     */
    private function parseSEOMetadata(string $response): array
    {
        try {
            // Try to extract JSON from response
            if (preg_match('/\{.*\}/s', $response, $matches)) {
                $data = json_decode($matches[0], true);
                if ($data) {
                    return $data;
                }
            }
            
            // Fallback parsing if JSON extraction fails
            return $this->fallbackParseSEOMetadata($response);
            
        } catch (\Exception $e) {
            $this->logger->warning('Failed to parse SEO metadata', ['error' => $e->getMessage()]);
            return $this->getDefaultSEOMetadata();
        }
    }

    /**
     * Parse sentiment analysis response
     */
    private function parseSentimentAnalysis(string $response): array
    {
        try {
            if (preg_match('/\{.*\}/s', $response, $matches)) {
                $data = json_decode($matches[0], true);
                if ($data) {
                    return $data;
                }
            }
            
            return $this->fallbackParseSentiment($response);
            
        } catch (\Exception $e) {
            $this->logger->warning('Failed to parse sentiment analysis', ['error' => $e->getMessage()]);
            return $this->getDefaultSentimentAnalysis();
        }
    }

    /**
     * Make HTTP request to Claude API
     */
    private function makeRequest(string $endpoint, array $data): array
    {
        $url = $this->baseUrl . '/' . $endpoint;
        
        $headers = [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $this->apiKey,
            'anthropic-version: 2023-06-01'
        ];

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($data),
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 60,
            CURLOPT_SSL_VERIFYPEER => true
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($error) {
            throw new \Exception("Claude API request failed: " . $error);
        }

        if ($httpCode !== 200) {
            $errorData = json_decode($response, true);
            $message = $errorData['error']['message'] ?? 'Unknown error';
            throw new \Exception("Claude API error ({$httpCode}): " . $message);
        }

        $decodedResponse = json_decode($response, true);
        if (!$decodedResponse) {
            throw new \Exception("Failed to decode Claude API response");
        }

        return $decodedResponse;
    }

    /**
     * Fallback SEO metadata parsing
     */
    private function fallbackParseSEOMetadata(string $response): array
    {
        // Simple regex-based parsing as fallback
        $metadata = [];
        
        if (preg_match('/title["\']?\s*:\s*["\']([^"\']+)["\']?/i', $response, $matches)) {
            $metadata['title'] = $matches[1];
        }
        
        if (preg_match('/description["\']?\s*:\s*["\']([^"\']+)["\']?/i', $response, $matches)) {
            $metadata['description'] = $matches[1];
        }
        
        return array_merge($this->getDefaultSEOMetadata(), $metadata);
    }

    /**
     * Fallback sentiment parsing
     */
    private function fallbackParseSentiment(string $response): array
    {
        $sentiment = $this->getDefaultSentimentAnalysis();
        
        // Try to extract sentiment score
        if (preg_match('/sentiment.*?(-?\d+\.?\d*)/i', $response, $matches)) {
            $sentiment['sentiment_score'] = (float)$matches[1];
        }
        
        return $sentiment;
    }

    /**
     * Default SEO metadata
     */
    private function getDefaultSEOMetadata(): array
    {
        return [
            'title' => 'Tiêu đề được tạo bởi AI',
            'description' => 'Mô tả được tạo bởi AI',
            'keywords' => ['ai', 'content', 'marketing'],
            'og_title' => 'Tiêu đề được tạo bởi AI',
            'og_description' => 'Mô tả được tạo bởi AI',
            'schema_suggestions' => ['Article', 'WebPage']
        ];
    }

    /**
     * Default sentiment analysis
     */
    private function getDefaultSentimentAnalysis(): array
    {
        return [
            'sentiment_score' => 0.0,
            'sentiment_label' => 'neutral',
            'emotions' => [
                'neutral' => 1.0
            ],
            'key_phrases' => [],
            'suggestions' => ['Không thể phân tích sentiment từ response']
        ];
    }
}