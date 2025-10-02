<?php

use Core\Database\Migration;

class AddAbTestingColumns extends Migration
{
    /**
     * Run the migration
     */
    public function up(\PDO $db): void
    {
        echo "📊 Adding missing columns to ab_tests table...\n";
        
        try {
            // Add hypothesis column
            $db->exec("ALTER TABLE ab_tests ADD COLUMN hypothesis TEXT");
            echo "  ✅ Added hypothesis column\n";
            
            // Add variable_tested column
            $db->exec("ALTER TABLE ab_tests ADD COLUMN variable_tested TEXT");
            echo "  ✅ Added variable_tested column\n";
            
            // Add control_value column
            $db->exec("ALTER TABLE ab_tests ADD COLUMN control_value TEXT");
            echo "  ✅ Added control_value column\n";
            
            // Add variant_value column
            $db->exec("ALTER TABLE ab_tests ADD COLUMN variant_value TEXT");
            echo "  ✅ Added variant_value column\n";
            
            // Add sample_size column
            $db->exec("ALTER TABLE ab_tests ADD COLUMN sample_size INTEGER DEFAULT 1000");
            echo "  ✅ Added sample_size column\n";
            
            // Update existing records with default values
            $db->exec("UPDATE ab_tests SET 
                hypothesis = 'Legacy test - no hypothesis recorded',
                variable_tested = 'landing_page',
                control_value = 'Original version',
                variant_value = 'Test version'
                WHERE hypothesis IS NULL");
            echo "  ✅ Updated existing records with default values\n";
            
            echo "🎯 A/B Testing columns migration completed successfully!\n";
            
        } catch (PDOException $e) {
            echo "❌ Migration failed: " . $e->getMessage() . "\n";
            throw $e;
        }
    }

    /**
     * Reverse the migration
     */
    public function down(\PDO $db): void
    {
        echo "📊 Removing A/B testing columns...\n";
        
        try {
            // SQLite doesn't support DROP COLUMN directly, so we need to recreate the table
            $db->exec("BEGIN TRANSACTION");
            
            // Create backup table
            $db->exec("CREATE TABLE ab_tests_backup AS SELECT 
                id, campaign_id, test_name, test_type, variant_a_config, variant_b_config,
                variant_a_traffic, variant_b_traffic, variant_a_conversions, variant_b_conversions,
                variant_a_revenue, variant_b_revenue, status, winner, confidence_level,
                start_date, end_date, created_at
                FROM ab_tests");
            
            // Drop original table
            $db->exec("DROP TABLE ab_tests");
            
            // Recreate original table structure
            $db->exec("CREATE TABLE ab_tests (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                campaign_id INTEGER,
                test_name TEXT NOT NULL,
                test_type TEXT DEFAULT 'landing_page',
                variant_a_config TEXT,
                variant_b_config TEXT,
                variant_a_traffic INTEGER DEFAULT 50,
                variant_b_traffic INTEGER DEFAULT 50,
                variant_a_conversions INTEGER DEFAULT 0,
                variant_b_conversions INTEGER DEFAULT 0,
                variant_a_revenue REAL DEFAULT 0,
                variant_b_revenue REAL DEFAULT 0,
                status TEXT DEFAULT 'running',
                winner TEXT,
                confidence_level REAL,
                start_date TEXT,
                end_date TEXT,
                created_at TEXT DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY(campaign_id) REFERENCES campaigns(id)
            )");
            
            // Restore data
            $db->exec("INSERT INTO ab_tests SELECT * FROM ab_tests_backup");
            
            // Drop backup table
            $db->exec("DROP TABLE ab_tests_backup");
            
            $db->exec("COMMIT");
            
            echo "  ✅ Removed A/B testing enhancement columns\n";
            echo "🔄 A/B Testing columns rollback completed!\n";
            
        } catch (PDOException $e) {
            $db->exec("ROLLBACK");
            echo "❌ Rollback failed: " . $e->getMessage() . "\n";
            throw $e;
        }
    }
}