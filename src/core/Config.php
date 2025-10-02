<?php

namespace Core;

/**
 * Configuration Management
 * Centralized configuration for the application
 */
class Config
{
    private array $config = [];

    public function __construct()
    {
        $this->loadDefaults();
        $this->loadFromEnvironment();
    }

    /**
     * Load default configuration
     */
    private function loadDefaults(): void
    {
        $this->config = [
            'app' => [
                'name' => 'CRM Digital Marketing',
                'version' => '2.0.0',
                'timezone' => 'Asia/Ho_Chi_Minh',
                'locale' => 'vi_VN',
            ],
            
            'database' => [
                'driver' => 'sqlite',
                'path' => __DIR__ . '/../crm.sqlite',
            ],

            'ai' => [
                'enabled' => true,
                'providers' => [
                    'openai' => [
                        'api_key' => getenv('OPENAI_API_KEY') ?: '',
                        'model' => 'gpt-4',
                        'max_tokens' => 2000,
                    ],
                    'claude' => [
                        'api_key' => getenv('CLAUDE_API_KEY') ?: '',
                        'model' => 'claude-3-sonnet-20240229',
                    ],
                    'gemini' => [
                        'api_key' => getenv('GEMINI_API_KEY') ?: '',
                        'model' => 'gemini-pro',
                    ],
                ],
                'content_generation' => [
                    'auto_publish' => false,
                    'review_required' => true,
                    'seo_optimization' => true,
                ],
            ],

            'social_media' => [
                'auto_posting' => false,
                'platforms' => [
                    'facebook' => [
                        'enabled' => false,
                        'app_id' => getenv('FACEBOOK_APP_ID') ?: '',
                        'app_secret' => getenv('FACEBOOK_APP_SECRET') ?: '',
                        'access_token' => getenv('FACEBOOK_ACCESS_TOKEN') ?: '',
                    ],
                    'instagram' => [
                        'enabled' => false,
                        'access_token' => getenv('INSTAGRAM_ACCESS_TOKEN') ?: '',
                    ],
                    'twitter' => [
                        'enabled' => false,
                        'api_key' => getenv('TWITTER_API_KEY') ?: '',
                        'api_secret' => getenv('TWITTER_API_SECRET') ?: '',
                        'access_token' => getenv('TWITTER_ACCESS_TOKEN') ?: '',
                        'access_token_secret' => getenv('TWITTER_ACCESS_TOKEN_SECRET') ?: '',
                    ],
                    'linkedin' => [
                        'enabled' => false,
                        'client_id' => getenv('LINKEDIN_CLIENT_ID') ?: '',
                        'client_secret' => getenv('LINKEDIN_CLIENT_SECRET') ?: '',
                        'access_token' => getenv('LINKEDIN_ACCESS_TOKEN') ?: '',
                    ],
                ],
            ],

            'automation' => [
                'queue_enabled' => true,
                'scheduler_enabled' => true,
                'workflows' => [
                    'content_publishing' => true,
                    'social_media_posting' => true,
                    'email_campaigns' => true,
                    'lead_nurturing' => true,
                ],
            ],

            'security' => [
                'csrf_protection' => true,
                'rate_limiting' => true,
                'api_rate_limit' => 1000, // requests per hour
            ],

            'logging' => [
                'level' => 'info',
                'file' => __DIR__ . '/../logs/app.log',
                'ai_actions' => true,
                'social_media_posts' => true,
            ],
        ];
    }

    /**
     * Load configuration from environment
     */
    private function loadFromEnvironment(): void
    {
        // Load .env file if exists
        $envFile = __DIR__ . '/../../.env';
        if (file_exists($envFile)) {
            $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            foreach ($lines as $line) {
                if (strpos($line, '=') !== false && $line[0] !== '#') {
                    [$key, $value] = explode('=', $line, 2);
                    $_ENV[trim($key)] = trim($value);
                }
            }
        }
    }

    /**
     * Get configuration value
     */
    public function get(string $key, $default = null)
    {
        $keys = explode('.', $key);
        $value = $this->config;

        foreach ($keys as $k) {
            if (isset($value[$k])) {
                $value = $value[$k];
            } else {
                return $default;
            }
        }

        return $value;
    }

    /**
     * Set configuration value
     */
    public function set(string $key, $value): void
    {
        $keys = explode('.', $key);
        $config = &$this->config;

        foreach ($keys as $k) {
            if (!isset($config[$k])) {
                $config[$k] = [];
            }
            $config = &$config[$k];
        }

        $config = $value;
    }
}