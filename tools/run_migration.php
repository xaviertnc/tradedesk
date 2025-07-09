<?php
/**
 * run_migration.php
 *
 * Migration Runner - 10 Jul 2025
 *
 * Purpose: Simple script to run the missing columns migration.
 *
 * @package FX-Trades-App
 * @author Assistant <assistant@example.com>
 *
 * @version 1.0 - INIT - 10 Jul 2025 - Initial commit.
 */

require_once 'MigrationService.php';

try {
  $db_file = 'data' . DIRECTORY_SEPARATOR . 'tradedesk.db';
  $pdo = new PDO( 'sqlite:' . $db_file );
  $pdo->setAttribute( PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION );
  
  $migrationService = new MigrationService( $pdo );
  $migrationService->runMigration( '2025_07_10_02_add_missing_columns.php' );
  
  echo "✅ Migration completed successfully!\n";
  
  // Verify the columns were added
  echo "\nVerifying schema...\n";
  
  $stmt = $pdo->query( "PRAGMA table_info(batches)" );
  $columns = $stmt->fetchAll( PDO::FETCH_COLUMN, 1 );
  
  if ( in_array( 'updated_at', $columns ) ) {
    echo "✅ updated_at column added to batches table\n";
  } else {
    echo "❌ updated_at column not found in batches table\n";
  }
  
  $stmt = $pdo->query( "PRAGMA table_info(trades)" );
  $columns = $stmt->fetchAll( PDO::FETCH_COLUMN, 1 );
  
  if ( in_array( 'last_error', $columns ) ) {
    echo "✅ last_error column added to trades table\n";
  } else {
    echo "❌ last_error column not found in trades table\n";
  }
  
} catch ( Exception $e ) {
  echo "❌ Migration failed: " . $e->getMessage() . "\n";
  exit( 1 );
} 