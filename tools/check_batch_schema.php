<?php
/**
 * tools/check_batch_schema.php
 *
 * Check Batch Schema - 10 Jul 2025
 *
 * Purpose: Check batches table schema and run migration if needed.
 *
 * @package FX-Trades-App
 * @author Gemini <gemini@google.com>
 *
 * Last 3 version commits:
 * @version 1.0 - INIT - 10 Jul 2025 - Initial commit.
 */

try {
  $pdo = new PDO( 'sqlite:C:\laragon\www\projects\currencyhub\tradedesk\v8\data\tradedesk.db' );
  $pdo->setAttribute( PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION );

  echo "Checking batches table schema...\n";

  $stmt = $pdo->query( 'PRAGMA table_info(batches)' );
  $columns = $stmt->fetchAll( PDO::FETCH_COLUMN, 1 );

  echo "Current columns: " . implode( ', ', $columns ) . "\n";

  // Check if concurrency columns exist
  $neededColumns = [
    'locked_at', 'locked_by', 'lock_timeout',
    'priority', 'queue_position', 'started_at', 'completed_at',
    'max_concurrent_trades', 'current_concurrent_trades'
  ];

  $missingColumns = array_diff( $neededColumns, $columns );

  if ( empty( $missingColumns ) ) {
    echo "✅ All concurrency columns already exist!\n";
  } else {
    echo "❌ Missing columns: " . implode( ', ', $missingColumns ) . "\n";
    echo "Running migration...\n";

    // Run the migration
    $migrationFile = 'C:\laragon\www\projects\currencyhub\tradedesk\v8\migrations\2025_07_10_03_add_batch_concurrency_columns.php';
    if ( file_exists( $migrationFile ) ) {
      $migration = require $migrationFile;
      $migration( $pdo );
      echo "✅ Migration completed successfully!\n";
    } else {
      echo "❌ Migration file not found!\n";
    }
  }

} catch ( Exception $e ) {
  echo "Error: " . $e->getMessage() . "\n";
} 