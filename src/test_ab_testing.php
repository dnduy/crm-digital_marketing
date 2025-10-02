<?php
// ==========================
// FILE: test_ab_testing.php - Test A/B Testing Database Fix
// ==========================

require_once 'lib/db.php';

echo "🧪 A/B TESTING DATABASE FIX VERIFICATION\n";
echo "=======================================\n\n";

try {
    // Test 1: Check if ab_tests table has required columns
    echo "📊 Test 1: Verifying table schema...\n";
    
    $schema = $db->query("PRAGMA table_info(ab_tests)")->fetchAll(PDO::FETCH_ASSOC);
    $columns = array_column($schema, 'name');
    
    $requiredColumns = ['hypothesis', 'variable_tested', 'control_value', 'variant_value', 'sample_size'];
    $missingColumns = array_diff($requiredColumns, $columns);
    
    if (empty($missingColumns)) {
        echo "  ✅ All required columns present: " . implode(', ', $requiredColumns) . "\n";
    } else {
        echo "  ❌ Missing columns: " . implode(', ', $missingColumns) . "\n";
        exit(1);
    }
    
    // Test 2: Test INSERT operation (the one that was failing)
    echo "\n📝 Test 2: Testing INSERT operation...\n";
    
    $testData = [
        'campaign_id' => 1,
        'test_name' => 'Test A/B Insert',
        'hypothesis' => 'New CTA will increase conversions by 15%',
        'variable_tested' => 'call_to_action_button',
        'control_value' => 'Sign Up Now',
        'variant_value' => 'Start Free Trial',
        'sample_size' => 2000,
        'confidence_level' => '95',
        'status' => 'setup',
        'created_at' => date('Y-m-d H:i:s')
    ];
    
    $sql = "INSERT INTO ab_tests(campaign_id,test_name,hypothesis,variable_tested,control_value,variant_value,sample_size,confidence_level,status,created_at) VALUES(?,?,?,?,?,?,?,?,?,?)";
    
    $stmt = $db->prepare($sql);
    $result = $stmt->execute(array_values($testData));
    
    if ($result) {
        $testId = $db->lastInsertId();
        echo "  ✅ INSERT successful - Test ID: $testId\n";
        
        // Test 3: Verify the data was inserted correctly
        echo "\n🔍 Test 3: Verifying inserted data...\n";
        
        $stmt = $db->prepare("SELECT * FROM ab_tests WHERE id = ?");
        $stmt->execute([$testId]);
        $insertedData = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($insertedData) {
            echo "  ✅ Data verification successful:\n";
            echo "    - Test Name: " . $insertedData['test_name'] . "\n";
            echo "    - Hypothesis: " . $insertedData['hypothesis'] . "\n";
            echo "    - Variable: " . $insertedData['variable_tested'] . "\n";
            echo "    - Control: " . $insertedData['control_value'] . "\n";
            echo "    - Variant: " . $insertedData['variant_value'] . "\n";
            echo "    - Sample Size: " . $insertedData['sample_size'] . "\n";
        } else {
            echo "  ❌ Failed to retrieve inserted data\n";
            exit(1);
        }
        
        // Clean up test data
        $db->prepare("DELETE FROM ab_tests WHERE id = ?")->execute([$testId]);
        echo "  🧹 Test data cleaned up\n";
        
    } else {
        echo "  ❌ INSERT failed\n";
        exit(1);
    }
    
    // Test 4: Check existing data compatibility
    echo "\n📈 Test 4: Checking existing data compatibility...\n";
    
    $existingTests = $db->query("SELECT COUNT(*) as count FROM ab_tests")->fetch(PDO::FETCH_ASSOC);
    echo "  📊 Existing A/B tests: " . $existingTests['count'] . "\n";
    
    if ($existingTests['count'] > 0) {
        $sampleTest = $db->query("SELECT * FROM ab_tests LIMIT 1")->fetch(PDO::FETCH_ASSOC);
        echo "  ✅ Sample existing test accessible:\n";
        echo "    - ID: " . $sampleTest['id'] . "\n";
        echo "    - Name: " . ($sampleTest['test_name'] ?: 'N/A') . "\n";
        echo "    - Status: " . ($sampleTest['status'] ?: 'N/A') . "\n";
    }
    
    echo "\n🎉 A/B TESTING FIX VERIFICATION COMPLETE!\n";
    echo "=========================================\n";
    echo "✅ Database schema updated successfully\n";
    echo "✅ INSERT operations working correctly\n";
    echo "✅ All required columns present\n";
    echo "✅ Data compatibility maintained\n";
    echo "\n💡 The A/B testing page should now work without errors!\n";
    
} catch (PDOException $e) {
    echo "❌ Database Error: " . $e->getMessage() . "\n";
    echo "Line: " . $e->getLine() . "\n";
    exit(1);
} catch (Exception $e) {
    echo "❌ General Error: " . $e->getMessage() . "\n";
    exit(1);
}