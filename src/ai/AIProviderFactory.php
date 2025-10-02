<?php

namespace AI;

use AI\Providers\OpenAIProvider;
use AI\Providers\ClaudeProvider;
use AI\Providers\GeminiProvider;
use Core\Logger;

/**
 * AI Provider Factory
 * Manages and creates AI provider instances
 */
class AIProviderFactory
{
    private array $config;
    private Logger $logger;
    private array $providers = [];

    public function __construct(array $config, Logger $logger)
    {
        $this->config = $config;
        $this->logger = $logger;
    }

    /**
     * Get AI provider by name
     */
    public function getProvider(string $providerName): AIProviderInterface
    {
        if (isset($this->providers[$providerName])) {
            return $this->providers[$providerName];
        }

        $provider = $this->createProvider($providerName);
        $this->providers[$providerName] = $provider;
        
        return $provider;
    }

    /**
     * Get the best provider for a specific task
     */
    public function getBestProvider(string $taskType = 'general'): AIProviderInterface
    {
        $providerRanking = $this->getProviderRanking($taskType);
        
        foreach ($providerRanking as $providerName) {
            if ($this->isProviderAvailable($providerName)) {
                return $this->getProvider($providerName);
            }
        }
        
        throw new \Exception("No AI providers available for task: {$taskType}");
    }

    /**
     * Get all available providers
     */
    public function getAvailableProviders(): array
    {
        $available = [];
        
        foreach ($this->getSupportedProviders() as $providerName) {
            if ($this->isProviderAvailable($providerName)) {
                $available[] = $providerName;
            }
        }
        
        return $available;
    }

    /**
     * Test provider connectivity
     */
    public function testProvider(string $providerName): array
    {
        try {
            $provider = $this->getProvider($providerName);
            
            $startTime = microtime(true);
            $testResponse = $provider->generateContent("Test connectivity", [
                'max_tokens' => 50,
                'temperature' => 0.1
            ]);
            $responseTime = microtime(true) - $startTime;
            
            return [
                'status' => 'success',
                'provider' => $providerName,
                'response_time' => round($responseTime * 1000, 2) . 'ms',
                'response_length' => strlen($testResponse),
                'timestamp' => date('Y-m-d H:i:s')
            ];
            
        } catch (\Exception $e) {
            return [
                'status' => 'error',
                'provider' => $providerName,
                'error' => $e->getMessage(),
                'timestamp' => date('Y-m-d H:i:s')
            ];
        }
    }

    /**
     * Load balance across multiple providers
     */
    public function getLoadBalancedProvider(string $taskType = 'general'): AIProviderInterface
    {
        $availableProviders = $this->getAvailableProviders();
        
        if (empty($availableProviders)) {
            throw new \Exception("No AI providers available");
        }

        // Simple round-robin load balancing
        $providerName = $this->getNextProvider($availableProviders, $taskType);
        
        return $this->getProvider($providerName);
    }

    /**
     * Get provider capabilities
     */
    public function getProviderCapabilities(string $providerName): array
    {
        $capabilities = [
            'openai' => [
                'max_tokens' => 4096,
                'supports_functions' => true,
                'supports_vision' => true,
                'languages' => ['en', 'vi', 'fr', 'es', 'de', 'ja', 'ko'],
                'strengths' => ['general', 'coding', 'analysis', 'creative'],
                'cost_per_1k_tokens' => 0.03,
                'rate_limits' => '3000 RPM'
            ],
            'claude' => [
                'max_tokens' => 100000,
                'supports_functions' => false,
                'supports_vision' => true,
                'languages' => ['en', 'vi', 'fr', 'es', 'de', 'ja'],
                'strengths' => ['analysis', 'writing', 'reasoning', 'safety'],
                'cost_per_1k_tokens' => 0.025,
                'rate_limits' => '1000 RPM'
            ],
            'gemini' => [
                'max_tokens' => 32768,
                'supports_functions' => true,
                'supports_vision' => true,
                'languages' => ['en', 'vi', 'fr', 'es', 'de', 'ja', 'ko', 'hi'],
                'strengths' => ['multimodal', 'reasoning', 'coding', 'factual'],
                'cost_per_1k_tokens' => 0.002,
                'rate_limits' => '60 RPM'
            ]
        ];

        return $capabilities[$providerName] ?? [];
    }

    /**
     * Get cost estimate for operation
     */
    public function getCostEstimate(string $providerName, int $estimatedTokens): array
    {
        $capabilities = $this->getProviderCapabilities($providerName);
        $costPer1k = $capabilities['cost_per_1k_tokens'] ?? 0.03;
        
        $estimatedCost = ($estimatedTokens / 1000) * $costPer1k;
        
        return [
            'provider' => $providerName,
            'estimated_tokens' => $estimatedTokens,
            'cost_per_1k_tokens' => $costPer1k,
            'estimated_cost_usd' => round($estimatedCost, 4),
            'estimated_cost_vnd' => round($estimatedCost * 24000, 0) // Approximate USD to VND
        ];
    }

    /**
     * Create provider instance
     */
    private function createProvider(string $providerName): AIProviderInterface
    {
        switch ($providerName) {
            case 'openai':
                return new OpenAIProvider(
                    $this->config['openai']['api_key'],
                    $this->config['openai']['model'] ?? 'gpt-4',
                    $this->config['openai']['max_tokens'] ?? 4096,
                    $this->logger
                );
                
            case 'claude':
                return new ClaudeProvider(
                    $this->config['claude']['api_key'],
                    $this->config['claude']['model'] ?? 'claude-3-sonnet-20240229',
                    $this->config['claude']['max_tokens'] ?? 4000,
                    $this->logger
                );
                
            case 'gemini':
                return new GeminiProvider(
                    $this->config['gemini']['api_key'],
                    $this->config['gemini']['model'] ?? 'gemini-pro',
                    $this->config['gemini']['max_tokens'] ?? 8192,
                    $this->logger
                );
                
            default:
                throw new \Exception("Unsupported AI provider: {$providerName}");
        }
    }

    /**
     * Check if provider is available
     */
    private function isProviderAvailable(string $providerName): bool
    {
        $providerConfig = $this->config[$providerName] ?? [];
        
        return !empty($providerConfig['api_key']) && 
               !empty($providerConfig['enabled']) &&
               $providerConfig['enabled'] === true;
    }

    /**
     * Get supported providers
     */
    private function getSupportedProviders(): array
    {
        return ['openai', 'claude', 'gemini'];
    }

    /**
     * Get provider ranking for specific task (public method)
     */
    public function getProviderRankingForTask(string $taskType): array
    {
        return $this->getProviderRanking($taskType);
    }

    /**
     * Get provider ranking for specific task
     */
    private function getProviderRanking(string $taskType): array
    {
        $rankings = [
            'general' => ['openai', 'claude', 'gemini'],
            'content_generation' => ['claude', 'openai', 'gemini'],
            'social_media' => ['gemini', 'openai', 'claude'],
            'seo_optimization' => ['openai', 'gemini', 'claude'],
            'sentiment_analysis' => ['claude', 'gemini', 'openai'],
            'content_improvement' => ['claude', 'openai', 'gemini'],
            'creative_writing' => ['claude', 'openai', 'gemini'],
            'technical_content' => ['openai', 'gemini', 'claude'],
            'multilingual' => ['gemini', 'openai', 'claude'],
            'cost_effective' => ['gemini', 'claude', 'openai']
        ];

        return $rankings[$taskType] ?? $rankings['general'];
    }

    /**
     * Get next provider for load balancing
     */
    private function getNextProvider(array $availableProviders, string $taskType): string
    {
        // Get ranking for task type
        $ranking = $this->getProviderRanking($taskType);
        
        // Filter ranking by available providers
        $availableRanked = array_intersect($ranking, $availableProviders);
        
        if (empty($availableRanked)) {
            return $availableProviders[0];
        }
        
        // Simple round-robin based on current minute
        $index = (int)(date('i')) % count($availableRanked);
        
        return array_values($availableRanked)[$index];
    }
}

/**
 * AI Provider Manager
 * High-level management and orchestration
 */
class AIProviderManager
{
    private AIProviderFactory $factory;
    private Logger $logger;
    private array $usage_stats = [];

    public function __construct(AIProviderFactory $factory, Logger $logger)
    {
        $this->factory = $factory;
        $this->logger = $logger;
    }

    /**
     * Generate content with automatic provider selection
     */
    public function generateContent(string $prompt, array $options = []): array
    {
        $taskType = $options['task_type'] ?? 'general';
        $useLoadBalancing = $options['load_balancing'] ?? false;
        $retryOnFailure = $options['retry_on_failure'] ?? true;
        
        try {
            $provider = $useLoadBalancing 
                ? $this->factory->getLoadBalancedProvider($taskType)
                : $this->factory->getBestProvider($taskType);
                
            $providerName = $this->getProviderName($provider);
            
            $startTime = microtime(true);
            $content = $provider->generateContent($prompt, $options);
            $processingTime = microtime(true) - $startTime;
            
            $this->recordUsage($providerName, $taskType, strlen($prompt), strlen($content), $processingTime);
            
            return [
                'success' => true,
                'content' => $content,
                'provider' => $providerName,
                'processing_time' => round($processingTime * 1000, 2) . 'ms',
                'prompt_length' => strlen($prompt),
                'response_length' => strlen($content)
            ];
            
        } catch (\Exception $e) {
            $this->logger->error('AI content generation failed', [
                'error' => $e->getMessage(),
                'task_type' => $taskType
            ]);
            
            if ($retryOnFailure) {
                return $this->retryWithDifferentProvider($prompt, $options, $taskType);
            }
            
            return [
                'success' => false,
                'error' => $e->getMessage(),
                'provider' => 'unknown'
            ];
        }
    }

    /**
     * Get usage statistics
     */
    public function getUsageStats(): array
    {
        return [
            'total_requests' => array_sum(array_column($this->usage_stats, 'requests')),
            'total_processing_time' => array_sum(array_column($this->usage_stats, 'processing_time')),
            'average_response_time' => $this->calculateAverageResponseTime(),
            'provider_usage' => $this->usage_stats,
            'most_used_provider' => $this->getMostUsedProvider(),
            'cost_estimates' => $this->getCostEstimates()
        ];
    }

    /**
     * Health check all providers
     */
    public function healthCheck(): array
    {
        $results = [];
        $availableProviders = $this->factory->getAvailableProviders();
        
        foreach ($availableProviders as $providerName) {
            $results[$providerName] = $this->factory->testProvider($providerName);
        }
        
        return [
            'timestamp' => date('Y-m-d H:i:s'),
            'total_providers' => count($availableProviders),
            'healthy_providers' => count(array_filter($results, fn($r) => $r['status'] === 'success')),
            'results' => $results
        ];
    }

    /**
     * Record usage statistics
     */
    private function recordUsage(string $provider, string $taskType, int $promptLength, int $responseLength, float $processingTime): void
    {
        if (!isset($this->usage_stats[$provider])) {
            $this->usage_stats[$provider] = [
                'requests' => 0,
                'total_prompt_length' => 0,
                'total_response_length' => 0,
                'processing_time' => 0,
                'task_types' => []
            ];
        }
        
        $this->usage_stats[$provider]['requests']++;
        $this->usage_stats[$provider]['total_prompt_length'] += $promptLength;
        $this->usage_stats[$provider]['total_response_length'] += $responseLength;
        $this->usage_stats[$provider]['processing_time'] += $processingTime;
        $this->usage_stats[$provider]['task_types'][$taskType] = 
            ($this->usage_stats[$provider]['task_types'][$taskType] ?? 0) + 1;
    }

    /**
     * Retry with different provider
     */
    private function retryWithDifferentProvider(string $prompt, array $options, string $taskType): array
    {
        try {
            $providers = $this->factory->getAvailableProviders();
            
            foreach ($providers as $providerName) {
                try {
                    $provider = $this->factory->getProvider($providerName);
                    $content = $provider->generateContent($prompt, $options);
                    
                    return [
                        'success' => true,
                        'content' => $content,
                        'provider' => $providerName,
                        'retry' => true
                    ];
                    
                } catch (\Exception $e) {
                    continue;
                }
            }
            
            throw new \Exception("All AI providers failed");
            
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => 'All providers failed: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Get provider name from instance
     */
    private function getProviderName(AIProviderInterface $provider): string
    {
        $className = get_class($provider);
        $parts = explode('\\', $className);
        $name = end($parts);
        
        return strtolower(str_replace('Provider', '', $name));
    }

    /**
     * Calculate average response time
     */
    private function calculateAverageResponseTime(): float
    {
        $totalTime = array_sum(array_column($this->usage_stats, 'processing_time'));
        $totalRequests = array_sum(array_column($this->usage_stats, 'requests'));
        
        return $totalRequests > 0 ? round($totalTime / $totalRequests * 1000, 2) : 0;
    }

    /**
     * Get most used provider
     */
    private function getMostUsedProvider(): string
    {
        if (empty($this->usage_stats)) {
            return 'none';
        }
        
        $maxRequests = 0;
        $mostUsed = '';
        
        foreach ($this->usage_stats as $provider => $stats) {
            if ($stats['requests'] > $maxRequests) {
                $maxRequests = $stats['requests'];
                $mostUsed = $provider;
            }
        }
        
        return $mostUsed;
    }

    /**
     * Get cost estimates for all providers
     */
    private function getCostEstimates(): array
    {
        $estimates = [];
        
        foreach ($this->usage_stats as $provider => $stats) {
            $estimatedTokens = intval($stats['total_prompt_length'] / 3 + $stats['total_response_length'] / 3); // Rough token estimation
            $estimates[$provider] = $this->factory->getCostEstimate($provider, $estimatedTokens);
        }
        
        return $estimates;
    }
}