<?php

namespace AI;

/**
 * AI Provider Interface
 * Contract for all AI service providers
 */
interface AIProviderInterface
{
    public function generateContent(string $prompt, array $options = []): string;
    public function improveContent(string $content, string $type = 'blog'): string;
    public function generateSocialMediaPost(string $content, string $platform = 'facebook'): string;
    public function generateSEOMetadata(string $content, string $targetKeyword = ''): array;
    public function analyzeContentSentiment(string $content): array;
}

/**
 * Content Generation Request
 */
class ContentRequest
{
    public string $type;
    public string $topic;
    public array $keywords;
    public int $wordCount;
    public string $tone;
    public string $language;
    public array $requirements;

    public function __construct(array $data)
    {
        $this->type = $data['type'] ?? 'blog';
        $this->topic = $data['topic'] ?? '';
        $this->keywords = $data['keywords'] ?? [];
        $this->wordCount = $data['word_count'] ?? 500;
        $this->tone = $data['tone'] ?? 'professional';
        $this->language = $data['language'] ?? 'vi';
        $this->requirements = $data['requirements'] ?? [];
    }
}

/**
 * Social Media Post Request
 */
class SocialMediaRequest
{
    public string $platform;
    public string $content;
    public array $hashtags;
    public ?string $imagePrompt;
    public array $targetAudience;
    public ?\DateTime $scheduledTime;

    public function __construct(array $data)
    {
        $this->platform = $data['platform'] ?? 'facebook';
        $this->content = $data['content'] ?? '';
        $this->hashtags = $data['hashtags'] ?? [];
        $this->imagePrompt = $data['image_prompt'] ?? null;
        $this->targetAudience = $data['target_audience'] ?? [];
        $this->scheduledTime = isset($data['scheduled_time']) ? new \DateTime($data['scheduled_time']) : null;
    }
}