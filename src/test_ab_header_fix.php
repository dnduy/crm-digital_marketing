<?php
// ==========================
// FILE: test_ab_header_fix.php - Test A/B Testing Header Warning Fix
// ==========================

echo "🚨 A/B TESTING HEADER WARNING FIX TEST\n";
echo "=====================================\n\n";

// Test 1: Check if the page loads without warnings
echo "📄 Test 1: Testing page load without warnings...\n";

ob_start();
$error_log = [];

// Capture errors
set_error_handler(function($severity, $message, $file, $line) use (&$error_log) {
    $error_log[] = [
        'severity' => $severity,
        'message' => $message,
        'file' => basename($file),
        'line' => $line
    ];
});

try {
    // Simulate the page loading process
    $_GET['action'] = 'ab_testing';
    $_GET['op'] = 'new';
    
    // Include necessary files
    require_once 'lib/db.php';
    require_once 'lib/auth.php';
    
    // Capture any output
    ob_start();
    
    // This would normally be called by index.php
    include 'views/ab_testing.php';
    
    $output = ob_get_clean();
    
    // Check for header warnings in the output
    $header_warnings = [];
    if (strpos($output, 'Cannot modify header information') !== false) {
        $header_warnings[] = 'Header modification warning found in output';
    }
    if (strpos($output, 'headers already sent') !== false) {
        $header_warnings[] = 'Headers already sent warning found in output';
    }
    
    if (empty($header_warnings)) {
        echo "  ✅ No header warnings detected in output\n";
    } else {
        echo "  ❌ Header warnings found:\n";
        foreach ($header_warnings as $warning) {
            echo "    - $warning\n";
        }
    }
    
    // Check captured errors
    $header_errors = array_filter($error_log, function($error) {
        return strpos($error['message'], 'header') !== false || 
               strpos($error['message'], 'Cannot modify') !== false;
    });
    
    if (empty($header_errors)) {
        echo "  ✅ No header-related PHP errors captured\n";
    } else {
        echo "  ❌ Header-related PHP errors found:\n";
        foreach ($header_errors as $error) {
            echo "    - {$error['message']} (Line: {$error['line']})\n";
        }
    }
    
} catch (Exception $e) {
    echo "  ❌ Exception occurred: " . $e->getMessage() . "\n";
}

restore_error_handler();

// Test 2: Check function structure
echo "\n🔍 Test 2: Analyzing function structure...\n";

$ab_testing_file = file_get_contents('views/ab_testing.php');

// Check that form processing comes before layout_header
$layout_header_pos = strpos($ab_testing_file, 'layout_header');
$first_post_check = strpos($ab_testing_file, "REQUEST_METHOD'] === 'POST'");

if ($first_post_check !== false && $first_post_check < $layout_header_pos) {
    echo "  ✅ Form processing occurs before layout_header call\n";
} else {
    echo "  ❌ Form processing may occur after layout_header call\n";
}

// Check for header() calls after layout_header
$header_calls = [];
$lines = explode("\n", $ab_testing_file);
$found_layout_header = false;

foreach ($lines as $num => $line) {
    if (strpos($line, 'layout_header') !== false) {
        $found_layout_header = true;
        continue;
    }
    
    if ($found_layout_header && strpos($line, "header('Location:") !== false) {
        $header_calls[] = $num + 1;
    }
}

if (empty($header_calls)) {
    echo "  ✅ No redirect headers found after layout_header call\n";
} else {
    echo "  ❌ Redirect headers found after layout_header on lines: " . implode(', ', $header_calls) . "\n";
}

// Test 3: Check for duplicate sections
echo "\n🔄 Test 3: Checking for duplicate code sections...\n";

$update_results_count = substr_count($ab_testing_file, "op === 'update_results'");
if ($update_results_count === 1) {
    echo "  ✅ No duplicate update_results sections found\n";
} else {
    echo "  ❌ Found $update_results_count update_results sections (should be 1)\n";
}

echo "\n🎯 HEADER FIX VERIFICATION SUMMARY\n";
echo "=================================\n";

$all_good = empty($header_warnings) && empty($header_errors) && 
           ($first_post_check < $layout_header_pos) && 
           empty($header_calls) && 
           ($update_results_count === 1);

if ($all_good) {
    echo "✅ ALL TESTS PASSED - Header warning fix successful!\n";
    echo "✅ Form processing occurs before output\n";
    echo "✅ No redirect headers after layout_header\n";
    echo "✅ No duplicate code sections\n";
    echo "✅ A/B testing page should work without warnings\n";
} else {
    echo "❌ Some issues detected - please review the problems above\n";
}

echo "\n💡 The A/B testing page should now handle form submissions correctly\n";
echo "   without generating 'headers already sent' warnings!\n";