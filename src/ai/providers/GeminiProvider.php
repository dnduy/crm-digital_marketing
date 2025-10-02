<?php

namespace AI\Providers;

use AI\AIProviderInterface;
use Core\Logger;

/**
 * Gemini (Google AI) Provider
 * Integrates with Google's Gemini API for content generation
 */
class GeminiProvider implements AIProviderInterface
{
    private string $apiKey;
    private string $model;
    private int $maxTokens;
    private Logger $logger;
    private string $baseUrl = 'https://generativelanguage.googleapis.com/v1beta';

    public function __construct(string $apiKey, string $model = 'gemini-pro', int $maxTokens = 8192, Logger $logger = null)
    {
        $this->apiKey = $apiKey;
        $this->model = $model;
        $this->maxTokens = $maxTokens;
        $this->logger = $logger ?: new Logger();
    }

    public function generateContent(string $prompt, array $options = []): string
    {
        $systemPrompt = $this->buildSystemPrompt($options);
        $fullPrompt = $systemPrompt . "\n\n" . $prompt;
        
        $data = [
            'contents' => [
                [
                    'parts' => [
                        ['text' => $fullPrompt]
                    ]
                ]
            ],
            'generationConfig' => [
                'temperature' => $options['temperature'] ?? 0.7,
                'topK' => $options['top_k'] ?? 40,
                'topP' => $options['top_p'] ?? 0.95,
                'maxOutputTokens' => $options['max_tokens'] ?? $this->maxTokens,
                'stopSequences' => $options['stop_sequences'] ?? []
            ],
            'safetySettings' => [
                [
                    'category' => 'HARM_CATEGORY_HARASSMENT',
                    'threshold' => 'BLOCK_MEDIUM_AND_ABOVE'
                ],
                [
                    'category' => 'HARM_CATEGORY_HATE_SPEECH',
                    'threshold' => 'BLOCK_MEDIUM_AND_ABOVE'
                ],
                [
                    'category' => 'HARM_CATEGORY_SEXUALLY_EXPLICIT',
                    'threshold' => 'BLOCK_MEDIUM_AND_ABOVE'
                ],
                [
                    'category' => 'HARM_CATEGORY_DANGEROUS_CONTENT',
                    'threshold' => 'BLOCK_MEDIUM_AND_ABOVE'
                ]
            ]
        ];

        try {
            $response = $this->makeRequest("models/{$this->model}:generateContent", $data);
            $content = $response['candidates'][0]['content']['parts'][0]['text'] ?? '';
            
            $this->logger->info('AI Content Generated', [
                'provider' => 'gemini',
                'model' => $this->model,
                'prompt_length' => strlen($prompt),
                'response_length' => strlen($content)
            ]);
            
            return $content;
        } catch (\Exception $e) {
            $this->logger->error('AI Content Generation Failed', [
                'provider' => 'gemini',
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    public function improveContent(string $content, string $type = 'blog'): string
    {
        $prompt = "Cải thiện nội dung {$type} sau đây theo các tiêu chí chất lượng cao:

🎯 NỘI DUNG CẦN CẢI THIỆN:
{$content}

📋 YÊU CẦU CẢI THIỆN:
✓ Tối ưu SEO với từ khóa tự nhiên
✓ Cải thiện cấu trúc và luồng thông tin
✓ Tăng tính hấp dẫn và engagement
✓ Sử dụng ngôn ngữ phù hợp với đối tượng Việt Nam
✓ Bổ sung call-to-action hiệu quả
✓ Đảm bảo tính chính xác và đáng tin cậy

🎨 STYLE GUIDE:
- Giữ nguyên tone gốc nhưng làm cho sinh động hơn
- Sử dụng bullet points và formatting phù hợp
- Thêm emojis nếu phù hợp với loại content
- Đảm bảo dễ đọc trên mobile";
        
        return $this->generateContent($prompt, [
            'type' => 'content_improvement',
            'content_type' => $type,
            'temperature' => 0.6  // Balanced creativity for improvement
        ]);
    }

    public function generateSocialMediaPost(string $content, string $platform = 'facebook'): string
    {
        $platformSpecs = $this->getSocialMediaSpecs($platform);
        $prompt = "Tạo một {$platform} post viral từ nội dung sau:

📝 NỘI DUNG GỐC:
{$content}

🎯 YÊU CẦU CHO {$platform}:
{$platformSpecs['instructions']}

📊 GIỚI HẠN:
- Tối đa {$platformSpecs['character_limit']} ký tự
- Sử dụng {$platformSpecs['hashtag_count']} hashtags phù hợp
- Có emoji thu hút {$platformSpecs['emoji_style']}

🚀 CHIẾN LƯỢC CONTENT:
- Hook mạnh mẽ ngay câu đầu
- Storytelling hấp dẫn (nếu có chỗ)
- Call-to-action rõ ràng
- Tận dụng trends hiện tại của {$platform}

Trả về post hoàn chỉnh ready để publish:";
        
        return $this->generateContent($prompt, [
            'type' => 'social_media',
            'platform' => $platform,
            'max_tokens' => $platformSpecs['max_tokens'],
            'temperature' => 0.9  // High creativity for social media
        ]);
    }

    public function generateSEOMetadata(string $content, string $targetKeyword = ''): array
    {
        $prompt = "Tạo SEO metadata tối ưu cho nội dung sau. Phân tích kỹ content và target keyword để tạo metadata hiệu quả nhất.

🎯 TARGET KEYWORD: {$targetKeyword}

📄 NỘI DUNG:
{$content}

🔍 YÊU CẦU SEO METADATA:

1. **TITLE TAG** (50-60 ký tự):
   - Chứa target keyword ở vị trí đầu
   - Hấp dẫn và click-worthy
   - Phù hợp với search intent

2. **META DESCRIPTION** (150-160 ký tự):
   - Tóm tắt hấp dẫn nội dung
   - Có target keyword tự nhiên
   - Call-to-action rõ ràng
   - Tạo desire click

3. **KEYWORDS** (5-10 từ khóa):
   - Primary keyword
   - LSI keywords
   - Long-tail variations
   - Related terms

4. **OPEN GRAPH**:
   - OG title tối ưu social sharing
   - OG description hấp dẫn

5. **SCHEMA MARKUP**:
   - Đề xuất schema types phù hợp
   - Structured data recommendations

📊 Trả về format JSON chuẩn:
```json
{
  \"title\": \"...\",
  \"description\": \"...\",
  \"keywords\": [...],
  \"og_title\": \"...\",
  \"og_description\": \"...\",
  \"schema_suggestions\": [...],
  \"seo_score\": 85,
  \"optimization_tips\": [...]
}
```";
        
        $response = $this->generateContent($prompt, [
            'type' => 'seo_metadata',
            'target_keyword' => $targetKeyword,
            'temperature' => 0.3  // Low temperature for structured output
        ]);

        return $this->parseSEOMetadata($response);
    }

    public function analyzeContentSentiment(string $content): array
    {
        $prompt = "Phân tích chi tiết sentiment và emotion của nội dung marketing sau. Cung cấp insights actionable cho content optimization.

📝 NỘI DUNG PHÂN TÍCH:
{$content}

🎯 YÊU CẦU PHÂN TÍCH:

1. **SENTIMENT OVERVIEW**:
   - Overall sentiment score (-1 to 1)
   - Sentiment classification
   - Confidence level

2. **EMOTION DETECTION**:
   - Primary emotions với scores
   - Secondary emotions
   - Emotional journey through content

3. **LINGUISTICS ANALYSIS**:
   - Key phrases ảnh hưởng sentiment
   - Tone markers
   - Language patterns

4. **MARKETING INSIGHTS**:
   - Brand perception implications
   - Audience response predictions
   - Content optimization opportunities

5. **ACTIONABLE RECOMMENDATIONS**:
   - Specific improvements
   - Tone adjustments
   - Engagement optimization

📊 Trả về JSON format chi tiết:
```json
{
  \"sentiment_score\": 0.75,
  \"sentiment_label\": \"positive\",
  \"confidence\": 0.92,
  \"emotions\": {
    \"joy\": 0.4,
    \"trust\": 0.3,
    \"excitement\": 0.25,
    \"anticipation\": 0.05
  },
  \"key_phrases\": {
    \"positive\": [...],
    \"negative\": [...],
    \"neutral\": [...]
  },
  \"tone_markers\": [...],
  \"marketing_insights\": {
    \"brand_perception\": \"...\",
    \"audience_response\": \"...\",
    \"conversion_potential\": \"high\"
  },
  \"recommendations\": [...]
}
```";
        
        $response = $this->generateContent($prompt, [
            'type' => 'sentiment_analysis',
            'temperature' => 0.1  // Very low temperature for analysis
        ]);

        return $this->parseSentimentAnalysis($response);
    }

    /**
     * Advanced content generation with Gemini's multimodal capabilities
     */
    public function generateContentWithContext(string $prompt, array $context = [], array $options = []): string
    {
        $contextPrompt = "";
        
        if (!empty($context['brand_voice'])) {
            $contextPrompt .= "\n🎭 BRAND VOICE: " . $context['brand_voice'];
        }
        
        if (!empty($context['target_audience'])) {
            $contextPrompt .= "\n👥 TARGET AUDIENCE: " . $context['target_audience'];
        }
        
        if (!empty($context['content_goals'])) {
            $contextPrompt .= "\n🎯 CONTENT GOALS: " . implode(', ', $context['content_goals']);
        }
        
        if (!empty($context['competitors_analysis'])) {
            $contextPrompt .= "\n🏢 COMPETITIVE LANDSCAPE: " . $context['competitors_analysis'];
        }
        
        $enhancedPrompt = $contextPrompt . "\n\n" . $prompt;
        
        return $this->generateContent($enhancedPrompt, array_merge($options, [
            'type' => 'contextual_content',
            'temperature' => 0.8
        ]));
    }

    /**
     * Build system prompt based on options
     */
    private function buildSystemPrompt(array $options): string
    {
        $basePrompt = "Bạn là AI Content Marketing Expert hàng đầu Việt Nam, chuyên tạo content chất lượng cao với khả năng understanding sâu về market Việt Nam và international best practices.";
        
        $type = $options['type'] ?? 'general';
        
        switch ($type) {
            case 'content_improvement':
                return $basePrompt . "\n\n🎯 CHUYÊN MÔNG: Content Optimization & Enhancement\n- Phân tích content existing và identify improvement opportunities\n- Apply SEO best practices và engagement optimization\n- Maintain brand voice consistency trong quá trình improvement\n- Focus vào conversion optimization và user experience";
                
            case 'social_media':
                $platform = $options['platform'] ?? 'facebook';
                return $basePrompt . "\n\n📱 CHUYÊN MÔNG: {$platform} Content Creation\n- Hiểu rõ algorithm và user behavior trên {$platform}\n- Tạo content viral potential cao\n- Optimize cho engagement metrics\n- Apply platform-specific best practices\n- Understand Vietnamese social media culture";
                
            case 'seo_metadata':
                return $basePrompt . "\n\n🔍 CHUYÊN MÔNG: SEO & Metadata Optimization\n- Expert trong technical SEO và on-page optimization\n- Hiểu rõ search intent và keyword research\n- Tạo metadata tối ưu cho both search engines và users\n- Apply structured data và schema markup knowledge\n- Focus vào CTR optimization";
                
            case 'sentiment_analysis':
                return $basePrompt . "\n\n🧠 CHUYÊN MÔNG: Content Psychology & Sentiment Analysis\n- Phân tích sâu emotion và psychological triggers\n- Understanding cultural context của Vietnamese audience\n- Identify brand perception implications\n- Provide actionable insights cho content optimization\n- Expert trong consumer psychology";
                
            case 'contextual_content':
                return $basePrompt . "\n\n🎨 CHUYÊN MÔNG: Strategic Content Creation\n- Tạo content với deep understanding về brand context\n- Apply advanced marketing psychology\n- Optimize cho specific business objectives\n- Balance creativity với conversion focus\n- Expert trong omnichannel content strategy";
                
            default:
                return $basePrompt . "\n\n💡 MISSION: Tạo content marketing đẳng cấp international với localization hoàn hảo cho thị trường Việt Nam";
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
                'max_tokens' => 600,
                'hashtag_count' => '3-5',
                'emoji_style' => 'moderate, professional',
                'instructions' => 'Facebook ưu tiên storytelling, community building, và meaningful conversations. Focus vào engagement quality hơn quantity. Có thể dài để kể story compelling.'
            ],
            'instagram' => [
                'character_limit' => 2200,
                'max_tokens' => 500,
                'hashtag_count' => '8-15',
                'emoji_style' => 'creative, visual-friendly',
                'instructions' => 'Instagram là visual-first platform. Caption hỗ trợ image/video story. Hashtag strategy rất quan trọng. Focus vào aesthetic và inspiration.'
            ],
            'twitter' => [
                'character_limit' => 280,
                'max_tokens' => 120,
                'hashtag_count' => '1-3',
                'emoji_style' => 'minimal, impactful',
                'instructions' => 'Twitter demand brevity và wit. Thread nếu cần elaborate. Real-time trends integration. Focus vào virality và discussion sparking.'
            ],
            'linkedin' => [
                'character_limit' => 1300,
                'max_tokens' => 400,
                'hashtag_count' => '3-5',
                'emoji_style' => 'professional, minimal',
                'instructions' => 'LinkedIn cần professional tone với thought leadership angle. Industry insights, business tips, career advice. B2B focused content performs best.'
            ],
            'tiktok' => [
                'character_limit' => 150,
                'max_tokens' => 100,
                'hashtag_count' => '3-6',
                'emoji_style' => 'fun, trendy',
                'instructions' => 'TikTok caption ngắn, support video content. Trend integration essential. Young audience focus. Hook trong 3 giây đầu.'
            ],
            'youtube' => [
                'character_limit' => 5000,
                'max_tokens' => 800,
                'hashtag_count' => '3-5',
                'emoji_style' => 'descriptive, engaging',
                'instructions' => 'YouTube description dài để SEO. Include timestamps, links, CTAs. Support video content với additional context và resources.'
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
            // Extract JSON from response
            if (preg_match('/```json\s*(.*?)\s*```/s', $response, $matches)) {
                $data = json_decode($matches[1], true);
                if ($data) return $data;
            }
            
            if (preg_match('/\{.*\}/s', $response, $matches)) {
                $data = json_decode($matches[0], true);
                if ($data) return $data;
            }
            
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
            if (preg_match('/```json\s*(.*?)\s*```/s', $response, $matches)) {
                $data = json_decode($matches[1], true);
                if ($data) return $data;
            }
            
            if (preg_match('/\{.*\}/s', $response, $matches)) {
                $data = json_decode($matches[0], true);
                if ($data) return $data;
            }
            
            return $this->fallbackParseSentiment($response);
            
        } catch (\Exception $e) {
            $this->logger->warning('Failed to parse sentiment analysis', ['error' => $e->getMessage()]);
            return $this->getDefaultSentimentAnalysis();
        }
    }

    /**
     * Make HTTP request to Gemini API
     */
    private function makeRequest(string $endpoint, array $data): array
    {
        $url = $this->baseUrl . '/' . $endpoint . '?key=' . $this->apiKey;
        
        $headers = [
            'Content-Type: application/json'
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
            throw new \Exception("Gemini API request failed: " . $error);
        }

        if ($httpCode !== 200) {
            $errorData = json_decode($response, true);
            $message = $errorData['error']['message'] ?? 'Unknown error';
            throw new \Exception("Gemini API error ({$httpCode}): " . $message);
        }

        $decodedResponse = json_decode($response, true);
        if (!$decodedResponse) {
            throw new \Exception("Failed to decode Gemini API response");
        }

        return $decodedResponse;
    }

    /**
     * Fallback SEO metadata parsing
     */
    private function fallbackParseSEOMetadata(string $response): array
    {
        $metadata = $this->getDefaultSEOMetadata();
        
        // Extract title
        if (preg_match('/title["\']?\s*[:=]\s*["\']([^"\']+)["\']?/i', $response, $matches)) {
            $metadata['title'] = trim($matches[1]);
        }
        
        // Extract description
        if (preg_match('/description["\']?\s*[:=]\s*["\']([^"\']+)["\']?/i', $response, $matches)) {
            $metadata['description'] = trim($matches[1]);
        }
        
        // Extract keywords
        if (preg_match('/keywords["\']?\s*[:=]\s*\[(.*?)\]/i', $response, $matches)) {
            $keywords = array_map('trim', explode(',', str_replace(['"', "'"], '', $matches[1])));
            $metadata['keywords'] = $keywords;
        }
        
        return $metadata;
    }

    /**
     * Fallback sentiment parsing
     */
    private function fallbackParseSentiment(string $response): array
    {
        $sentiment = $this->getDefaultSentimentAnalysis();
        
        // Extract sentiment score
        if (preg_match('/sentiment.*?score["\']?\s*[:=]\s*(-?\d+\.?\d*)/i', $response, $matches)) {
            $sentiment['sentiment_score'] = (float)$matches[1];
        }
        
        // Extract sentiment label
        if (preg_match('/sentiment.*?label["\']?\s*[:=]\s*["\']?(\w+)["\']?/i', $response, $matches)) {
            $sentiment['sentiment_label'] = strtolower($matches[1]);
        }
        
        return $sentiment;
    }

    /**
     * Default SEO metadata
     */
    private function getDefaultSEOMetadata(): array
    {
        return [
            'title' => 'Tiêu đề được tạo bởi Gemini AI',
            'description' => 'Mô tả SEO được tạo bởi Gemini AI',
            'keywords' => ['ai', 'gemini', 'content', 'marketing', 'vietnam'],
            'og_title' => 'Tiêu đề được tạo bởi Gemini AI',
            'og_description' => 'Mô tả OG được tạo bởi Gemini AI',
            'schema_suggestions' => ['Article', 'WebPage', 'BlogPosting'],
            'seo_score' => 75,
            'optimization_tips' => ['Thêm target keyword vào title', 'Cải thiện meta description']
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
            'confidence' => 0.5,
            'emotions' => [
                'neutral' => 1.0
            ],
            'key_phrases' => [
                'positive' => [],
                'negative' => [],
                'neutral' => []
            ],
            'tone_markers' => [],
            'marketing_insights' => [
                'brand_perception' => 'neutral',
                'audience_response' => 'moderate',
                'conversion_potential' => 'medium'
            ],
            'recommendations' => ['Không thể phân tích sentiment từ Gemini response']
        ];
    }
}