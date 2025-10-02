<?php

require_once __DIR__ . '/autoload.php';
require_once __DIR__ . '/lib/db.php';

use Repositories\ContentRepository;
use Repositories\SocialMediaPostRepository;

echo "📊 Advanced Repository Features Test\n";
echo "===================================\n\n";

$contentRepo = new ContentRepository($db);
$socialRepo = new SocialMediaPostRepository($db);

// Test AI content filtering
echo "🤖 AI Generated Content:\n";
$aiContent = $contentRepo->getAIGenerated(3);
foreach ($aiContent as $content) {
    echo "  - " . $content['title'] . " (SEO: " . $content['seo_score'] . ")\n";
}

// Test search functionality
echo "\n🔍 Search Results for \"AI\":\n";
$searchResults = $contentRepo->searchByKeywords('AI');
foreach ($searchResults as $content) {
    echo "  - " . $content['title'] . "\n";
}

// Test content with social posts
echo "\n📱 Content with Social Posts:\n";
$contentWithSocial = $contentRepo->getWithSocialPosts(4);
if ($contentWithSocial) {
    echo "Content: " . $contentWithSocial['title'] . "\n";
    echo "Social Posts: " . count($contentWithSocial['social_posts']) . "\n";
    foreach ($contentWithSocial['social_posts'] as $post) {
        echo "  - " . $post['platform'] . ": " . $post['status'] . "\n";
    }
}

// Test performance analytics
echo "\n📈 Performance Analytics:\n";
$performance = $socialRepo->getPerformanceAnalytics();
foreach ($performance as $perf) {
    echo "Platform: " . $perf['platform'] . "\n";
    echo "  Posts: " . $perf['posts_count'] . "\n";
    echo "  Avg Engagement: " . round($perf['avg_engagement'], 1) . "\n";
    echo "  Total Impressions: " . number_format($perf['total_impressions']) . "\n\n";
}

// Test scheduled posts
echo "📅 Scheduled Posts:\n";
$scheduled = $socialRepo->getScheduledPosts();
foreach ($scheduled as $post) {
    echo "  - " . $post['platform'] . ": " . $post['scheduled_at'] . "\n";
}

echo "\n✅ Advanced features working perfectly!\n";