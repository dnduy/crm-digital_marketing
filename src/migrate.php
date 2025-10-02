<?php

require_once __DIR__ . '/autoload.php';
require_once __DIR__ . '/lib/db.php';

use Core\Database\MigrationManager;
use Core\Logger;

/**
 * Database Migration CLI Tool
 * Run migrations from command line
 */

function showHelp()
{
    echo "Database Migration Tool\n";
    echo "======================\n\n";
    echo "Usage: php migrate.php [command] [options]\n\n";
    echo "Commands:\n";
    echo "  migrate          Run pending migrations\n";
    echo "  rollback         Rollback last migration\n";
    echo "  status           Show migration status\n";
    echo "  reset            Reset all migrations\n";
    echo "  fresh            Reset and re-run all migrations\n";
    echo "  create [name]    Create new migration file\n";
    echo "  help             Show this help message\n\n";
    echo "Examples:\n";
    echo "  php migrate.php migrate\n";
    echo "  php migrate.php create add_user_preferences_table\n";
    echo "  php migrate.php status\n";
}

function main($args)
{
    global $db;
    
    $logger = new Logger();
    $migrationManager = new MigrationManager($db, $logger);

    $command = $args[1] ?? 'help';

    try {
        switch ($command) {
            case 'migrate':
                echo "Running migrations...\n";
                $executed = $migrationManager->migrate();
                
                if (empty($executed)) {
                    echo "No pending migrations to run.\n";
                } else {
                    echo "Executed migrations:\n";
                    foreach ($executed as $migration) {
                        echo "  - {$migration}\n";
                    }
                    echo "\nMigrations completed successfully!\n";
                }
                break;

            case 'rollback':
                echo "Rolling back last migration...\n";
                $rolledBack = $migrationManager->rollback();
                
                if ($rolledBack) {
                    echo "Rolled back: {$rolledBack}\n";
                } else {
                    echo "No migrations to rollback.\n";
                }
                break;

            case 'status':
                echo "Migration Status:\n";
                echo "================\n\n";
                $status = $migrationManager->status();
                
                if (empty($status)) {
                    echo "No migrations found.\n";
                } else {
                    foreach ($status as $migration) {
                        $status = $migration['executed'] ? '✅ Executed' : '⏳ Pending';
                        $executedAt = $migration['executed_at'] ? " ({$migration['executed_at']})" : '';
                        echo "{$status} - {$migration['migration']}{$executedAt}\n";
                    }
                }
                break;

            case 'reset':
                echo "Resetting all migrations...\n";
                $rolledBack = $migrationManager->reset();
                
                if (empty($rolledBack)) {
                    echo "No migrations to reset.\n";
                } else {
                    echo "Reset migrations:\n";
                    foreach ($rolledBack as $migration) {
                        echo "  - {$migration}\n";
                    }
                    echo "\nAll migrations reset successfully!\n";
                }
                break;

            case 'fresh':
                echo "Running fresh migrations (reset + migrate)...\n";
                $executed = $migrationManager->fresh();
                
                echo "Fresh migrations executed:\n";
                foreach ($executed as $migration) {
                    echo "  - {$migration}\n";
                }
                echo "\nFresh migrations completed successfully!\n";
                break;

            case 'create':
                $name = $args[2] ?? null;
                
                if (!$name) {
                    echo "Error: Migration name is required.\n";
                    echo "Usage: php migrate.php create migration_name\n";
                    exit(1);
                }
                
                $filename = $migrationManager->createMigration($name);
                echo "Migration created: {$filename}\n";
                echo "Edit the file to add your migration logic.\n";
                break;

            case 'help':
            default:
                showHelp();
                break;
        }
    } catch (Exception $e) {
        echo "Error: " . $e->getMessage() . "\n";
        exit(1);
    }
}

// Run the migration tool
main($argv);