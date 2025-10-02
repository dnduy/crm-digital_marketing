<?php

namespace Core\Database;

use PDO;

/**
 * Base Migration Class
 * All migrations should extend this class
 */
abstract class Migration
{
    /**
     * Run the migration
     */
    abstract public function up(PDO $db): void;

    /**
     * Reverse the migration
     */
    abstract public function down(PDO $db): void;

    /**
     * Helper method to create table
     */
    protected function createTable(PDO $db, string $tableName, array $columns, array $options = []): void
    {
        $columnDefinitions = [];
        
        foreach ($columns as $name => $definition) {
            $columnDefinitions[] = "{$name} {$definition}";
        }
        
        $sql = "CREATE TABLE {$tableName} (\n    " . implode(",\n    ", $columnDefinitions) . "\n)";
        
        if (!empty($options)) {
            $sql .= " " . implode(" ", $options);
        }
        
        $db->exec($sql);
    }

    /**
     * Helper method to drop table
     */
    protected function dropTable(PDO $db, string $tableName): void
    {
        $db->exec("DROP TABLE IF EXISTS {$tableName}");
    }

    /**
     * Helper method to add column
     */
    protected function addColumn(PDO $db, string $tableName, string $columnName, string $definition): void
    {
        $sql = "ALTER TABLE {$tableName} ADD COLUMN {$columnName} {$definition}";
        $db->exec($sql);
    }

    /**
     * Helper method to drop column (SQLite limitation workaround)
     */
    protected function dropColumn(PDO $db, string $tableName, string $columnName): void
    {
        // SQLite doesn't support DROP COLUMN directly
        // This is a simplified approach - in production you'd need to recreate the table
        $this->addNote("Warning: SQLite doesn't support DROP COLUMN. Manual intervention may be required.");
    }

    /**
     * Helper method to add index
     */
    protected function addIndex(PDO $db, string $tableName, array $columns, string $indexName = null): void
    {
        $indexName = $indexName ?: "idx_{$tableName}_" . implode('_', $columns);
        $columnList = implode(', ', $columns);
        
        $sql = "CREATE INDEX {$indexName} ON {$tableName} ({$columnList})";
        $db->exec($sql);
    }

    /**
     * Helper method to drop index
     */
    protected function dropIndex(PDO $db, string $indexName): void
    {
        $db->exec("DROP INDEX IF EXISTS {$indexName}");
    }

    /**
     * Helper method to insert seed data
     */
    protected function insertData(PDO $db, string $tableName, array $data): void
    {
        if (empty($data)) {
            return;
        }

        $columns = array_keys($data[0]);
        $placeholders = str_repeat('?,', count($columns) - 1) . '?';
        
        $sql = "INSERT INTO {$tableName} (" . implode(', ', $columns) . ") VALUES ({$placeholders})";
        $stmt = $db->prepare($sql);
        
        foreach ($data as $row) {
            $stmt->execute(array_values($row));
        }
    }

    /**
     * Add migration note/warning
     */
    protected function addNote(string $message): void
    {
        echo "NOTE: {$message}\n";
    }

    /**
     * Common column definitions
     */
    protected function id(): string
    {
        return "INTEGER PRIMARY KEY AUTOINCREMENT";
    }

    protected function string(string $name, int $length = 255): string
    {
        return "{$name} VARCHAR({$length})";
    }

    protected function text(string $name): string
    {
        return "{$name} TEXT";
    }

    protected function integer(string $name): string
    {
        return "{$name} INTEGER";
    }

    protected function boolean(string $name): string
    {
        return "{$name} BOOLEAN DEFAULT FALSE";
    }

    protected function timestamp(string $name): string
    {
        return "{$name} TIMESTAMP";
    }

    protected function timestamps(): array
    {
        return [
            'created_at' => 'TIMESTAMP DEFAULT CURRENT_TIMESTAMP',
            'updated_at' => 'TIMESTAMP DEFAULT CURRENT_TIMESTAMP'
        ];
    }

    protected function foreignKey(string $name, string $references): string
    {
        return "{$name} INTEGER REFERENCES {$references}";
    }

    protected function json(string $name): string
    {
        return "{$name} TEXT"; // SQLite stores JSON as TEXT
    }
}