<?php
/**
 * run_migration.php
 *
 * Migration Runner - 10 Jul 2025
 *
 * Purpose: Script to run database migrations.
 *
 * @package FX-Trades-App
 * @author Assistant <assistant@example.com>
 *
 * @version 1.1 - UPD - 10 Jul 2025 - Accept migration filename as parameter
 * @version 1.0 - INIT - 10 Jul 2025 - Initial commit.
 */

require_once __DIR__ . '/../MigrationService.php';

// Get migration filename from command line argument
$migrationFile = $argv[1] ?? null;

if ( ! $migrationFile ) {
  echo "Usage: php run_migration.php <migration_filename>\n";
  echo "Example: php run_migration.php 2025_07_10_03_add_missing_updated_at.php\n";
  exit( 1 );
}

try {
  $db_file = __DIR__ . '/../data/tradedesk.db';
  $pdo = new PDO( 'sqlite:' . $db_file );
  $pdo->setAttribute( PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION );
  
  $migrationService = new MigrationService( $pdo );
  $migrationService->runMigration( $migrationFile );
  
  echo "✅ Migration '{$migrationFile}' completed successfully!\n";
  
} catch ( Exception $e ) {
  echo "❌ Migration failed: " . $e->getMessage() . "\n";
  exit( 1 );
} 