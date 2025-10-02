<?php

require_once __DIR__ . '/autoload.php';
require_once __DIR__ . '/lib/db.php';

use Repositories\ContentRepository;
use Repositories\SocialMediaPostRepository;

/**
 * Seed AI-Enhanced Sample Data
 */

echo "🌱 Seeding AI-Enhanced Sample Data\n";
echo "==================================\n\n";

$contentRepo = new ContentRepository($db);
$socialRepo = new SocialMediaPostRepository($db);

// Create AI-generated content samples
$aiContents = [
    [
        'title' => 'The Future of AI in Digital Marketing 2025',
        'slug' => 'ai-digital-marketing-2025',
        'content' => 'Artificial Intelligence is revolutionizing digital marketing by enabling hyper-personalized customer experiences, predictive analytics, and automated content generation. In 2025, we see AI tools transforming how businesses engage with customers, optimize campaigns, and drive measurable ROI. From chatbots that understand context to AI that creates compelling ad copy, the landscape is evolving rapidly.',
        'meta_title' => 'AI in Digital Marketing: Complete 2025 Guide',
        'meta_description' => 'Discover how AI is transforming digital marketing in 2025 with personalization, automation, and predictive analytics. Learn implementation strategies and best practices.',
        'meta_keywords' => 'AI marketing, digital marketing automation, artificial intelligence, machine learning, personalization',
        'target_keywords' => 'AI digital marketing, marketing automation 2025, artificial intelligence marketing',
        'content_type' => 'blog',
        'status' => 'published',
        'seo_score' => 92,
        'ai_generated' => true,
        'author_id' => 1,
        'target_platforms' => 'linkedin,facebook,twitter'
    ],
    [
        'title' => 'Social Media Automation: Balance Efficiency and Authenticity',
        'slug' => 'social-media-automation-guide',
        'content' => 'Effective social media automation requires finding the perfect balance between efficiency and authentic human connection. While automation can save hours of manual work, the key is knowing what to automate and what requires human touch. This comprehensive guide explores best practices for social media automation that maintains genuine engagement.',
        'meta_title' => 'Social Media Automation Best Practices 2025',
        'meta_description' => 'Learn how to implement social media automation while maintaining authentic engagement. Best practices, tools, and strategies for effective automation.',
        'meta_keywords' => 'social media automation, social media management, content scheduling, automated posting',
        'target_keywords' => 'social media automation, automated social posting, social media tools',
        'content_type' => 'guide',
        'status' => 'published',
        'seo_score' => 88,
        'ai_generated' => true,
        'author_id' => 1,
        'target_platforms' => 'instagram,facebook,twitter,linkedin'
    ],
    [
        'title' => 'Content Strategy Trends: What Works in 2025',
        'slug' => 'content-strategy-trends-2025',
        'content' => 'Content strategy in 2025 is shaped by AI-powered personalization, interactive media, and data-driven optimization. Successful content creators are leveraging artificial intelligence to understand audience preferences, predict trending topics, and optimize content performance in real-time.',
        'meta_title' => 'Content Strategy Trends 2025: AI-Powered Approaches',
        'meta_description' => 'Explore the latest content strategy trends for 2025 including AI personalization, interactive content, and data-driven optimization techniques.',
        'meta_keywords' => 'content strategy, content marketing trends, AI content, personalization, interactive content',
        'target_keywords' => 'content strategy 2025, AI content marketing, content personalization',
        'content_type' => 'article',
        'status' => 'published',
        'seo_score' => 85,
        'ai_generated' => false,
        'author_id' => 1,
        'target_platforms' => 'linkedin,facebook'
    ],
    [
        'title' => 'Email Marketing Automation with AI Personalization',
        'slug' => 'email-marketing-ai-automation',
        'content' => 'Email marketing automation powered by AI is transforming how businesses communicate with customers. Smart segmentation, predictive send times, and personalized content generation are driving unprecedented engagement rates and conversion improvements.',
        'meta_title' => 'AI Email Marketing Automation: Complete Guide',
        'meta_description' => 'Master AI-powered email marketing automation with smart segmentation, predictive timing, and personalized content generation.',
        'meta_keywords' => 'email marketing automation, AI email marketing, email personalization, automated email campaigns',
        'target_keywords' => 'AI email marketing, email automation, personalized email campaigns',
        'content_type' => 'guide',
        'status' => 'draft',
        'seo_score' => 80,
        'ai_generated' => true,
        'author_id' => 1,
        'target_platforms' => 'linkedin,twitter'
    ]
];

echo "📝 Creating AI-enhanced content...\n";
$contentIds = [];
foreach ($aiContents as $content) {
    $id = $contentRepo->create($content);
    $contentIds[] = $id;
    echo "   ✅ Created: {$content['title']} (ID: {$id})\n";
}

// Create social media posts for the content
$socialPosts = [
    [
        'content_id' => $contentIds[0],
        'platform' => 'linkedin',
        'content' => '🚀 The AI revolution in digital marketing is here!

In 2025, AI is transforming how we:
✅ Personalize customer experiences at scale
✅ Predict campaign performance with 95% accuracy  
✅ Generate compelling content in minutes
✅ Optimize ad spend automatically

What AI marketing tools are game-changers for your business? Share your experience below! 👇

#AI #DigitalMarketing #MarketingAutomation #ArtificialIntelligence #MarketingStrategy #BusinessGrowth',
        'hashtags' => 'AI,DigitalMarketing,MarketingAutomation,ArtificialIntelligence,MarketingStrategy,BusinessGrowth',
        'status' => 'published',
        'published_at' => date('Y-m-d H:i:s', strtotime('-2 hours')),
        'platform_post_id' => 'ln_' . uniqid(),
        'platform_url' => 'https://linkedin.com/posts/activity-' . uniqid(),
        'analytics_data' => json_encode([
            'impressions' => 3250,
            'engagement' => 147,
            'clicks' => 85,
            'reactions' => 62,
            'shares' => 23,
            'comments' => 18
        ])
    ],
    [
        'content_id' => $contentIds[0],
        'platform' => 'facebook',
        'content' => '🤖 AI is changing the game in digital marketing! 

From personalized customer journeys to predictive analytics, artificial intelligence is helping businesses:

💡 Increase conversion rates by 25%
📈 Reduce customer acquisition costs by 40%
🎯 Improve targeting accuracy by 60%
⚡ Save 15+ hours per week on content creation

Ready to harness the power of AI for your marketing? Our latest guide covers everything you need to know! 

#AIMarketing #DigitalTransformation #MarketingAutomation #BusinessGrowth',
        'hashtags' => 'AIMarketing,DigitalTransformation,MarketingAutomation,BusinessGrowth',
        'status' => 'published',
        'published_at' => date('Y-m-d H:i:s', strtotime('-3 hours')),
        'platform_post_id' => 'fb_' . uniqid(),
        'platform_url' => 'https://facebook.com/post/' . uniqid(),
        'analytics_data' => json_encode([
            'impressions' => 2800,
            'engagement' => 95,
            'clicks' => 48,
            'reactions' => 42,
            'shares' => 15,
            'comments' => 12
        ])
    ],
    [
        'content_id' => $contentIds[1],
        'platform' => 'instagram',
        'content' => '✨ Social media automation doesn\'t mean losing the human touch! 

The secret sauce? Knowing what to automate and what needs that personal touch 👥

🤖 AUTOMATE:
• Content scheduling
• Basic responses
• Performance tracking
• Hashtag research

💬 KEEP HUMAN:
• Community conversations
• Crisis management  
• Creative strategy
• Relationship building

What\'s your automation strategy? Drop your tips below! 👇

#SocialMediaAutomation #MarketingTips #ContentStrategy #SocialMediaManager #DigitalMarketing #MarketingAutomation #SocialMediaTips #ContentCreator #MarketingHacks #BusinessGrowth',
        'hashtags' => 'SocialMediaAutomation,MarketingTips,ContentStrategy,SocialMediaManager,DigitalMarketing,MarketingAutomation,SocialMediaTips,ContentCreator,MarketingHacks,BusinessGrowth',
        'status' => 'scheduled',
        'scheduled_at' => date('Y-m-d H:i:s', strtotime('+2 hours')),
        'image_url' => 'https://example.com/images/social-automation-tips.jpg'
    ],
    [
        'content_id' => $contentIds[1],
        'platform' => 'twitter',
        'content' => '🚀 Social media automation in 2025:

✅ Schedule posts for optimal engagement times
✅ Auto-respond to common questions  
✅ Track performance metrics automatically
✅ Use AI for hashtag optimization

But remember: automation amplifies strategy, it doesn\'t replace creativity! 🎨

What\'s your biggest automation win? 

#SocialMediaAutomation #MarketingTips #ContentStrategy',
        'hashtags' => 'SocialMediaAutomation,MarketingTips,ContentStrategy',
        'status' => 'published',
        'published_at' => date('Y-m-d H:i:s', strtotime('-1 hour')),
        'platform_post_id' => 'tw_' . uniqid(),
        'platform_url' => 'https://twitter.com/status/' . uniqid(),
        'analytics_data' => json_encode([
            'impressions' => 1850,
            'engagement' => 78,
            'clicks' => 35,
            'retweets' => 12,
            'likes' => 45,
            'replies' => 8
        ])
    ],
    [
        'content_id' => $contentIds[2],
        'platform' => 'linkedin',
        'content' => '📊 Content strategy trends dominating 2025:

1️⃣ AI-powered personalization at scale
2️⃣ Interactive content driving 3x engagement
3️⃣ Real-time content optimization
4️⃣ Voice and video-first strategies
5️⃣ Community-driven content creation

The brands winning are those using data to understand their audience deeply and AI to deliver precisely what they want, when they want it.

What content trends are you seeing in your industry? 

#ContentStrategy #ContentMarketing #AI #DigitalMarketing #MarketingTrends',
        'hashtags' => 'ContentStrategy,ContentMarketing,AI,DigitalMarketing,MarketingTrends',
        'status' => 'draft'
    ]
];

echo "\n📱 Creating social media posts...\n";
foreach ($socialPosts as $post) {
    $id = $socialRepo->create($post);
    echo "   ✅ Created {$post['platform']} post for content #{$post['content_id']} (ID: {$id})\n";
}

echo "\n📊 Database Statistics:\n";
echo "======================\n";

// Content stats
$contentStats = $contentRepo->getAnalyticsSummary();
echo "Content Summary:\n";
foreach ($contentStats as $stat) {
    echo "  - {$stat['content_type']}: {$stat['count']} items (AI: {$stat['ai_generated_count']}, Published: {$stat['published_count']})\n";
}

// Social media stats  
$socialStats = $socialRepo->getPlatformStats();
echo "\nSocial Media Summary:\n";
foreach ($socialStats as $stat) {
    echo "  - {$stat['platform']}: {$stat['total_posts']} posts (Published: {$stat['published_posts']}, Scheduled: {$stat['scheduled_posts']})\n";
}

// Performance check
echo "\n🎯 Performance Test:\n";
$topContent = $contentRepo->getTopPerforming(3);
echo "Top Performing Content:\n";
foreach ($topContent as $content) {
    echo "  - {$content['title']} (SEO: {$content['seo_score']}, Posts: {$content['post_count']})\n";
}

echo "\n✅ Sample data seeded successfully!\n";
echo "🚀 Database system is fully operational with AI-enhanced content!\n";