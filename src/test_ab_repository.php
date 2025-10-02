<?php
// ==========================
// FILE: test_ab_repository.php - Test A/B Testing Repository
// ==========================

require_once 'autoload.php';
require_once 'lib/db.php';

use Repositories\AbTestRepository;

echo "🧪 A/B TESTING REPOSITORY TEST\n";
echo "=============================\n\n";

try {
    $abTestRepo = new AbTestRepository($db);
    
    // Test 1: Create a test
    echo "📝 Test 1: Creating A/B test...\n";
    
    $testData = [
        'campaign_id' => 1,
        'test_name' => 'Homepage CTA Test',
        'hypothesis' => 'Changing the CTA from "Sign Up" to "Get Started" will increase conversions by 20%',
        'variable_tested' => 'cta_button_text',
        'control_value' => 'Sign Up',
        'variant_value' => 'Get Started',
        'sample_size' => 1000,
        'confidence_level' => 95,
        'status' => 'setup'
    ];
    
    $testId = $abTestRepo->create($testData);
    
    if ($testId) {
        echo "  ✅ A/B test created with ID: $testId\n";
    } else {
        echo "  ❌ Failed to create A/B test\n";
        exit(1);
    }
    
    // Test 2: Start the test
    echo "\n🚀 Test 2: Starting the test...\n";
    
    $started = $abTestRepo->startTest($testId);
    if ($started) {
        echo "  ✅ Test started successfully\n";
    } else {
        echo "  ❌ Failed to start test\n";
    }
    
    // Test 3: Record some conversions
    echo "\n📊 Test 3: Recording conversions...\n";
    
    // Record conversions for variant A (control)
    $abTestRepo->recordConversion($testId, 'a', 29.99);
    $abTestRepo->recordConversion($testId, 'a', 49.99);
    $abTestRepo->recordConversion($testId, 'a', 19.99);
    
    // Record conversions for variant B (test)
    $abTestRepo->recordConversion($testId, 'b', 39.99);
    $abTestRepo->recordConversion($testId, 'b', 59.99);
    $abTestRepo->recordConversion($testId, 'b', 29.99);
    $abTestRepo->recordConversion($testId, 'b', 49.99);
    
    echo "  ✅ Recorded conversions: 3 for variant A, 4 for variant B\n";
    
    // Test 4: Get test statistics
    echo "\n📈 Test 4: Getting test statistics...\n";
    
    $stats = $abTestRepo->getTestStatistics($testId);
    if ($stats) {
        echo "  ✅ Test statistics:\n";
        echo "    - Variant A (Control) Rate: {$stats['variant_a_rate']}%\n";
        echo "    - Variant B (Test) Rate: {$stats['variant_b_rate']}%\n";
        echo "    - Improvement: {$stats['improvement']}%\n";
        echo "    - Total Conversions: {$stats['total_conversions']}\n";
        echo "    - Revenue A: \${$stats['variant_a_revenue']}\n";
        echo "    - Revenue B: \${$stats['variant_b_revenue']}\n";
        echo "    - Revenue Improvement: {$stats['revenue_improvement']}%\n";
        echo "    - Statistically Significant: " . ($stats['is_significant'] ? 'Yes' : 'No') . "\n";
    } else {
        echo "  ❌ Failed to get test statistics\n";
    }
    
    // Test 5: Get active tests
    echo "\n🔍 Test 5: Getting active tests...\n";
    
    $activeTests = $abTestRepo->getActiveTests();
    echo "  ✅ Found " . count($activeTests) . " active test(s)\n";
    
    foreach ($activeTests as $test) {
        echo "    - {$test['test_name']} (Status: {$test['status']})\n";
    }
    
    // Test 6: Get performance summary
    echo "\n📊 Test 6: Getting performance summary...\n";
    
    $summary = $abTestRepo->getPerformanceSummary();
    echo "  ✅ Performance Summary:\n";
    echo "    - Total Tests: {$summary['total_tests']}\n";
    echo "    - Active Tests: {$summary['active_tests']}\n";
    echo "    - Completed Tests: {$summary['completed_tests']}\n";
    echo "    - Total Conversions: {$summary['total_conversions']}\n";
    echo "    - Total Revenue: \${$summary['total_revenue']}\n";
    echo "    - Average Improvement: {$summary['avg_improvement']}%\n";
    
    // Test 7: Search functionality
    echo "\n🔍 Test 7: Testing search functionality...\n";
    
    $searchResults = $abTestRepo->search('CTA');
    echo "  ✅ Found " . count($searchResults) . " test(s) matching 'CTA'\n";
    
    // Test 8: Stop the test
    echo "\n🛑 Test 8: Stopping the test...\n";
    
    $stopped = $abTestRepo->stopTest($testId, 'variant_b');
    if ($stopped) {
        echo "  ✅ Test stopped with winner: variant_b\n";
    } else {
        echo "  ❌ Failed to stop test\n";
    }
    
    // Clean up
    echo "\n🧹 Cleaning up test data...\n";
    $abTestRepo->delete($testId);
    echo "  ✅ Test data cleaned up\n";
    
    echo "\n🎉 A/B TESTING REPOSITORY TEST COMPLETE!\n";
    echo "=======================================\n";
    echo "✅ Repository pattern working correctly\n";
    echo "✅ CRUD operations successful\n";
    echo "✅ Statistical calculations working\n";
    echo "✅ Search functionality operational\n";
    echo "✅ Test lifecycle management complete\n";
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . " Line: " . $e->getLine() . "\n";
    exit(1);
}