<?php
// ==========================
// FILE: /lib/social/LinkedInPlatform.php
// LinkedIn Platform Handler with Professional Focus
// ==========================

require_once __DIR__ . '/SocialMediaPlatformInterface.php';

class LinkedInPlatform extends AbstractSocialMediaPlatform {
    private $clientId;
    private $clientSecret;
    private $personId;
    
    protected function initializePlatform(): void {
        $this->platformName = 'linkedin';
        $this->apiBaseUrl = 'https://api.linkedin.com/v2/';
        $this->rateLimits = [
            'posts_per_hour' => 100,
            'posts_per_day' => 1000
        ];
    }
    
    public function authenticate(array $credentials): bool {
        try {
            $this->clientId = $credentials['client_id'] ?? '';
            $this->clientSecret = $credentials['client_secret'] ?? '';
            $this->accessToken = $credentials['access_token'] ?? '';
            
            if ($this->validateToken()) {
                // Get person ID for posting
                $profileInfo = $this->getAccountInfo();
                $this->personId = $profileInfo['account_id'] ?? null;
                return true;
            }
            
            return false;
        } catch (Exception $e) {
            $this->logActivity('authentication_failed', ['error' => $e->getMessage()]);
            return false;
        }
    }
    
    public function createPost(array $postData): array {
        try {
            if (!$this->checkRateLimit()) {
                throw new Exception('Rate limit exceeded');
            }
            
            $content = $this->sanitizeContent($postData['content'] ?? '');
            if (strlen($content) > 3000) {
                throw new Exception('Content exceeds 3000 character limit');
            }
            
            // LinkedIn posting requires specific format
            $shareData = [
                'author' => 'urn:li:person:' . $this->personId,
                'lifecycleState' => 'PUBLISHED',
                'specificContent' => [
                    'com.linkedin.ugc.ShareContent' => [
                        'shareCommentary' => [
                            'text' => $content
                        ],
                        'shareMediaCategory' => 'NONE'
                    ]
                ],
                'visibility' => [
                    'com.linkedin.ugc.MemberNetworkVisibility' => 'PUBLIC'
                ]
            ];
            
            // Handle media attachments
            if (!empty($postData['media_urls'])) {
                $mediaUrn = $this->uploadMediaForSharing($postData['media_urls'][0]);
                if ($mediaUrn) {
                    $shareData['specificContent']['com.linkedin.ugc.ShareContent']['shareMediaCategory'] = 'IMAGE';
                    $shareData['specificContent']['com.linkedin.ugc.ShareContent']['media'] = [
                        [
                            'status' => 'READY',
                            'description' => [
                                'text' => $postData['media_description'] ?? ''
                            ],
                            'media' => $mediaUrn,
                            'title' => [
                                'text' => $postData['media_title'] ?? ''
                            ]
                        ]
                    ];
                }
            }
            
            // Handle article sharing
            if (!empty($postData['article_url'])) {
                $shareData['specificContent']['com.linkedin.ugc.ShareContent']['shareMediaCategory'] = 'ARTICLE';
                $shareData['specificContent']['com.linkedin.ugc.ShareContent']['media'] = [
                    [
                        'status' => 'READY',
                        'originalUrl' => $postData['article_url'],
                        'title' => [
                            'text' => $postData['article_title'] ?? ''
                        ],
                        'description' => [
                            'text' => $postData['article_description'] ?? ''
                        ]
                    ]
                ];
            }
            
            $response = $this->makeApiRequest('ugcPosts', $shareData, 'POST');
            
            $this->logActivity('post_created', [
                'post_id' => $response['id'] ?? null,
                'content_length' => strlen($content)
            ]);
            
            return [
                'success' => true,
                'post_id' => $response['id'] ?? null,
                'platform_response' => $response
            ];
            
        } catch (Exception $e) {
            return $this->handleApiError($e);
        }
    }
    
    public function schedulePost(array $postData, DateTime $scheduleTime): array {
        // LinkedIn doesn't support native scheduling via API
        return [
            'success' => false,
            'error' => 'LinkedIn API does not support native post scheduling',
            'suggested_solution' => 'Use LinkedIn native scheduler or third-party tools'
        ];
    }
    
    public function getAccountInfo(): array {
        try {
            // Get basic profile information
            $response = $this->makeApiRequest('people/~:(id,firstName,lastName,headline,publicProfileUrl,profilePicture(displayImage~:mediaType))');
            
            $profileData = $response;
            $personId = $profileData['id'] ?? null;
            
            // Get follower count (requires additional API call)
            $followerCount = 0;
            try {
                $followersResponse = $this->makeApiRequest("networkSizes/urn:li:person:{$personId}?edgeType=CompanyFollowedByMember");
                $followerCount = $followersResponse['firstDegreeSize'] ?? 0;
            } catch (Exception $e) {
                // Follower count might not be available
            }
            
            return [
                'success' => true,
                'account_id' => $personId,
                'username' => null, // LinkedIn doesn't have usernames
                'display_name' => ($profileData['firstName']['localized']['en_US'] ?? '') . ' ' . ($profileData['lastName']['localized']['en_US'] ?? ''),
                'headline' => $profileData['headline']['localized']['en_US'] ?? '',
                'profile_url' => $profileData['publicProfileUrl'] ?? null,
                'followers_count' => $followerCount,
                'connections_count' => 'Private', // LinkedIn keeps this private
                'profile_image_url' => $this->extractProfileImageUrl($profileData),
                'raw_data' => $profileData
            ];
            
        } catch (Exception $e) {
            return $this->handleApiError($e);
        }
    }
    
    public function getAnalytics(array $options = []): array {
        try {
            $startDate = $options['start_date'] ?? date('Y-m-d', strtotime('-30 days'));
            $endDate = $options['end_date'] ?? date('Y-m-d');
            
            // Get organization analytics (requires company page access)
            $analyticsData = [];
            
            // Note: LinkedIn analytics require special permissions and company page access
            // This is a simplified version for personal profiles
            
            return [
                'success' => true,
                'analytics' => $analyticsData,
                'note' => 'LinkedIn analytics require company page access or LinkedIn Marketing Developer Platform',
                'available_metrics' => [
                    'profile_views' => 'Requires LinkedIn Premium',
                    'post_impressions' => 'Limited data available',
                    'engagement_metrics' => 'Limited data available'
                ]
            ];
            
        } catch (Exception $e) {
            return $this->handleApiError($e);
        }
    }
    
    public function getAudienceInsights(): array {
        try {
            // LinkedIn audience insights are very limited for personal profiles
            $accountInfo = $this->getAccountInfo();
            
            return [
                'success' => true,
                'total_connections' => 'Private data',
                'followers_count' => $accountInfo['followers_count'] ?? 0,
                'industry_insights' => 'Requires LinkedIn Company Page',
                'geographic_insights' => 'Requires LinkedIn Company Page',
                'demographic_insights' => 'Requires LinkedIn Company Page',
                'note' => 'Detailed audience insights require LinkedIn Company Page and Marketing API access'
            ];
            
        } catch (Exception $e) {
            return $this->handleApiError($e);
        }
    }
    
    public function uploadMedia(string $filePath, string $type = 'image'): array {
        try {
            if (!file_exists($filePath)) {
                throw new Exception('Media file not found');
            }
            
            // Step 1: Register upload
            $registerData = [
                'registerUploadRequest' => [
                    'recipes' => ['urn:li:digitalmediaRecipe:feedshare-image'],
                    'owner' => 'urn:li:person:' . $this->personId,
                    'serviceRelationships' => [
                        [
                            'relationshipType' => 'OWNER',
                            'identifier' => 'urn:li:userGeneratedContent'
                        ]
                    ]
                ]
            ];
            
            $registerResponse = $this->makeApiRequest('assets?action=registerUpload', $registerData, 'POST');
            
            $uploadUrl = $registerResponse['value']['uploadMechanism']['com.linkedin.digitalmedia.uploading.MediaUploadHttpRequest']['uploadUrl'] ?? null;
            $asset = $registerResponse['value']['asset'] ?? null;
            
            if (!$uploadUrl || !$asset) {
                throw new Exception('Failed to register media upload');
            }
            
            // Step 2: Upload binary data
            $mediaData = file_get_contents($filePath);
            
            $ch = curl_init();
            curl_setopt_array($ch, [
                CURLOPT_URL => $uploadUrl,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => $mediaData,
                CURLOPT_HTTPHEADER => [
                    'Authorization: Bearer ' . $this->accessToken,
                    'Content-Type: application/octet-stream'
                ],
                CURLOPT_TIMEOUT => 60
            ]);
            
            $uploadResponse = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            
            if ($httpCode !== 201) {
                throw new Exception('Media upload failed');
            }
            
            return [
                'success' => true,
                'media_urn' => $asset,
                'upload_response' => $uploadResponse
            ];
            
        } catch (Exception $e) {
            return $this->handleApiError($e);
        }
    }
    
    public function deletePost(string $postId): bool {
        try {
            $response = $this->makeApiRequest("ugcPosts/{$postId}", [], 'DELETE');
            
            $this->logActivity('post_deleted', ['post_id' => $postId]);
            
            return true; // LinkedIn DELETE doesn't return content, assumes success if no error
            
        } catch (Exception $e) {
            $this->logActivity('delete_failed', ['post_id' => $postId, 'error' => $e->getMessage()]);
            return false;
        }
    }
    
    public function getPostingGuidelines(): array {
        return [
            'max_content_length' => 3000,
            'max_media_count' => 1,
            'supported_media_types' => ['image/jpeg', 'image/png'],
            'max_media_size' => '100MB',
            'hashtag_recommendations' => 'Use 3-5 professional hashtags',
            'posting_frequency' => 'Max 100 posts per hour, 1000 per day',
            'best_posting_times' => [
                'Tuesday-Thursday: 8:00-10:00',
                'Tuesday-Wednesday: 12:00-14:00'
            ],
            'content_recommendations' => [
                'Professional tone',
                'Industry insights',
                'Thought leadership',
                'Company updates',
                'Career content'
            ],
            'engagement_tips' => [
                'Ask questions to encourage comments',
                'Share industry insights',
                'Use relevant hashtags',
                'Tag relevant connections',
                'Include call-to-action'
            ]
        ];
    }
    
    public function supportsContentType(string $type): bool {
        $supportedTypes = ['text', 'image', 'article', 'document'];
        return in_array($type, $supportedTypes);
    }
    
    public function validateToken(): bool {
        try {
            $response = $this->makeApiRequest('people/~:(id)');
            return isset($response['id']);
        } catch (Exception $e) {
            return false;
        }
    }
    
    private function uploadMediaForSharing(string $mediaUrl): ?string {
        $tempFile = tempnam(sys_get_temp_dir(), 'linkedin_media_');
        file_put_contents($tempFile, file_get_contents($mediaUrl));
        
        $result = $this->uploadMedia($tempFile);
        unlink($tempFile);
        
        return $result['success'] ? $result['media_urn'] : null;
    }
    
    private function extractProfileImageUrl(array $profileData): ?string {
        $displayImage = $profileData['profilePicture']['displayImage~']['elements'] ?? [];
        
        if (!empty($displayImage)) {
            // Get the largest available image
            $largestImage = end($displayImage);
            return $largestImage['identifiers'][0]['identifier'] ?? null;
        }
        
        return null;
    }
    
    protected function makeApiRequest(string $endpoint, array $data = [], string $method = 'GET'): array {
        $url = $this->apiBaseUrl . $endpoint;
        
        $headers = [
            'Authorization: Bearer ' . $this->accessToken,
            'Content-Type: application/json',
            'X-Restli-Protocol-Version: 2.0.0'
        ];
        
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_SSL_VERIFYPEER => false
        ]);
        
        if ($method === 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        } elseif ($method === 'DELETE') {
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'DELETE');
        }
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);
        
        if ($error) {
            throw new Exception("LinkedIn API request failed: " . $error);
        }
        
        $decodedResponse = json_decode($response, true);
        
        if ($httpCode >= 400) {
            $errorMsg = 'Unknown LinkedIn API error';
            if (isset($decodedResponse['message'])) {
                $errorMsg = $decodedResponse['message'];
            } elseif (isset($decodedResponse['error_description'])) {
                $errorMsg = $decodedResponse['error_description'];
            }
            throw new Exception("LinkedIn API error ({$httpCode}): " . $errorMsg);
        }
        
        return $decodedResponse ?: [];
    }
}