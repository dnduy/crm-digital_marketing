<?php

namespace Core\Database;

use PDO;
use Core\Logger;

/**
 * Database Migration System
 * Handles schema changes and database versioning
 */
class MigrationManager
{
    private PDO $db;
    private Logger $logger;
    private string $migrationsPath;
    private string $migrationsTable = 'migrations';

    public function __construct(PDO $db, Logger $logger = null, string $migrationsPath = null)
    {
        $this->db = $db;
        $this->logger = $logger ?: new Logger();
        $this->migrationsPath = $migrationsPath ?: __DIR__ . '/../../migrations';
        $this->initializeMigrationsTable();
    }

    /**
     * Run pending migrations
     */
    public function migrate(): array
    {
        $pendingMigrations = $this->getPendingMigrations();
        $executed = [];

        foreach ($pendingMigrations as $migration) {
            try {
                $this->executeMigration($migration);
                $executed[] = $migration;
                $this->logger->info("Migration executed: {$migration}");
            } catch (\Exception $e) {
                $this->logger->error("Migration failed: {$migration}", [
                    'error' => $e->getMessage()
                ]);
                throw $e;
            }
        }

        return $executed;
    }

    /**
     * Rollback last migration
     */
    public function rollback(): ?string
    {
        $lastMigration = $this->getLastMigration();
        
        if (!$lastMigration) {
            $this->logger->info('No migrations to rollback');
            return null;
        }

        try {
            $this->rollbackMigration($lastMigration);
            $this->logger->info("Migration rolled back: {$lastMigration}");
            return $lastMigration;
        } catch (\Exception $e) {
            $this->logger->error("Rollback failed: {$lastMigration}", [
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Get migration status
     */
    public function status(): array
    {
        $allMigrations = $this->getAllMigrationFiles();
        $executedMigrations = $this->getExecutedMigrations();
        
        $status = [];
        
        foreach ($allMigrations as $migration) {
            $status[] = [
                'migration' => $migration,
                'executed' => in_array($migration, $executedMigrations),
                'executed_at' => $this->getMigrationExecutedAt($migration)
            ];
        }

        return $status;
    }

    /**
     * Create new migration file
     */
    public function createMigration(string $name): string
    {
        $timestamp = date('Y_m_d_His');
        $className = $this->studlyCase($name);
        $filename = "{$timestamp}_{$name}.php";
        $filepath = $this->migrationsPath . '/' . $filename;

        $template = $this->getMigrationTemplate($className);

        if (!is_dir($this->migrationsPath)) {
            mkdir($this->migrationsPath, 0755, true);
        }

        file_put_contents($filepath, $template);

        $this->logger->info("Migration created: {$filename}");
        return $filename;
    }

    /**
     * Reset all migrations
     */
    public function reset(): array
    {
        $executedMigrations = array_reverse($this->getExecutedMigrations());
        $rolledBack = [];

        foreach ($executedMigrations as $migration) {
            try {
                $this->rollbackMigration($migration);
                $rolledBack[] = $migration;
            } catch (\Exception $e) {
                $this->logger->error("Reset failed at: {$migration}", [
                    'error' => $e->getMessage()
                ]);
                throw $e;
            }
        }

        return $rolledBack;
    }

    /**
     * Fresh migration (reset + migrate)
     */
    public function fresh(): array
    {
        $this->reset();
        return $this->migrate();
    }

    /**
     * Initialize migrations table
     */
    private function initializeMigrationsTable(): void
    {
        $sql = "CREATE TABLE IF NOT EXISTS {$this->migrationsTable} (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            migration VARCHAR(255) NOT NULL,
            batch INTEGER NOT NULL,
            executed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )";

        $this->db->exec($sql);
    }

    /**
     * Get pending migrations
     */
    private function getPendingMigrations(): array
    {
        $allMigrations = $this->getAllMigrationFiles();
        $executedMigrations = $this->getExecutedMigrations();
        
        return array_diff($allMigrations, $executedMigrations);
    }

    /**
     * Get all migration files
     */
    private function getAllMigrationFiles(): array
    {
        if (!is_dir($this->migrationsPath)) {
            return [];
        }

        $files = scandir($this->migrationsPath);
        $migrations = [];

        foreach ($files as $file) {
            if (pathinfo($file, PATHINFO_EXTENSION) === 'php' && $file !== '.' && $file !== '..') {
                $migrations[] = pathinfo($file, PATHINFO_FILENAME);
            }
        }

        sort($migrations);
        return $migrations;
    }

    /**
     * Get executed migrations
     */
    private function getExecutedMigrations(): array
    {
        $stmt = $this->db->prepare("SELECT migration FROM {$this->migrationsTable} ORDER BY batch, id");
        $stmt->execute();
        
        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    }

    /**
     * Get last executed migration
     */
    private function getLastMigration(): ?string
    {
        $stmt = $this->db->prepare("SELECT migration FROM {$this->migrationsTable} ORDER BY batch DESC, id DESC LIMIT 1");
        $stmt->execute();
        
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result ? $result['migration'] : null;
    }

    /**
     * Execute a migration
     */
    private function executeMigration(string $migration): void
    {
        $migrationFile = $this->migrationsPath . '/' . $migration . '.php';
        
        if (!file_exists($migrationFile)) {
            throw new \Exception("Migration file not found: {$migrationFile}");
        }

        require_once $migrationFile;
        
        $className = $this->getMigrationClassName($migration);
        
        if (!class_exists($className)) {
            throw new \Exception("Migration class not found: {$className}");
        }

        $migrationInstance = new $className();
        
        if (!method_exists($migrationInstance, 'up')) {
            throw new \Exception("Migration {$className} must have an 'up' method");
        }

        $this->db->beginTransaction();

        try {
            $migrationInstance->up($this->db);
            
            // Record migration execution
            $batch = $this->getNextBatch();
            $stmt = $this->db->prepare("INSERT INTO {$this->migrationsTable} (migration, batch) VALUES (?, ?)");
            $stmt->execute([$migration, $batch]);
            
            $this->db->commit();
        } catch (\Exception $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    /**
     * Rollback a migration
     */
    private function rollbackMigration(string $migration): void
    {
        $migrationFile = $this->migrationsPath . '/' . $migration . '.php';
        
        if (!file_exists($migrationFile)) {
            throw new \Exception("Migration file not found: {$migrationFile}");
        }

        require_once $migrationFile;
        
        $className = $this->getMigrationClassName($migration);
        $migrationInstance = new $className();
        
        if (!method_exists($migrationInstance, 'down')) {
            throw new \Exception("Migration {$className} must have a 'down' method for rollback");
        }

        $this->db->beginTransaction();

        try {
            $migrationInstance->down($this->db);
            
            // Remove migration record
            $stmt = $this->db->prepare("DELETE FROM {$this->migrationsTable} WHERE migration = ?");
            $stmt->execute([$migration]);
            
            $this->db->commit();
        } catch (\Exception $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    /**
     * Get next batch number
     */
    private function getNextBatch(): int
    {
        $stmt = $this->db->prepare("SELECT MAX(batch) FROM {$this->migrationsTable}");
        $stmt->execute();
        
        $maxBatch = $stmt->fetchColumn();
        return $maxBatch ? $maxBatch + 1 : 1;
    }

    /**
     * Get migration class name from filename
     */
    private function getMigrationClassName(string $migration): string
    {
        // Remove timestamp prefix: 2023_10_02_123456_create_users_table -> create_users_table
        $parts = explode('_', $migration);
        $nameParts = array_slice($parts, 4); // Skip timestamp parts
        
        return $this->studlyCase(implode('_', $nameParts));
    }

    /**
     * Get migration executed at timestamp
     */
    private function getMigrationExecutedAt(string $migration): ?string
    {
        $stmt = $this->db->prepare("SELECT executed_at FROM {$this->migrationsTable} WHERE migration = ?");
        $stmt->execute([$migration]);
        
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result ? $result['executed_at'] : null;
    }

    /**
     * Convert snake_case to StudlyCase
     */
    private function studlyCase(string $value): string
    {
        return str_replace(' ', '', ucwords(str_replace(['-', '_'], ' ', $value)));
    }

    /**
     * Get migration template
     */
    private function getMigrationTemplate(string $className): string
    {
        return "<?php

use Core\Database\Migration;

class {$className} extends Migration
{
    /**
     * Run the migration
     */
    public function up(\PDO \$db): void
    {
        // Add your migration logic here
        \$sql = \"CREATE TABLE example_table (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            name VARCHAR(255) NOT NULL,
            email VARCHAR(255) UNIQUE,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )\";
        
        \$db->exec(\$sql);
    }

    /**
     * Reverse the migration
     */
    public function down(\PDO \$db): void
    {
        // Add your rollback logic here
        \$db->exec(\"DROP TABLE IF EXISTS example_table\");
    }
}";
    }
}