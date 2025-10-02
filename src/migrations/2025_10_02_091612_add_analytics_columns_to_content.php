<?php

use Core\Database\Migration;

class AddAnalyticsColumnsToContent extends Migration
{
    /**
     * Run the migration
     */
    public function up(\PDO $db): void
    {
        // Add your migration logic here
        $sql = "CREATE TABLE example_table (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            name VARCHAR(255) NOT NULL,
            email VARCHAR(255) UNIQUE,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )";
        
        $db->exec($sql);
    }

    /**
     * Reverse the migration
     */
    public function down(\PDO $db): void
    {
        // Add your rollback logic here
        $db->exec("DROP TABLE IF EXISTS example_table");
    }
}