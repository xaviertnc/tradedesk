<?php
/**
 * migrations/2025_07_10_02_add_missing_columns.php
 *
 * DB Migration: Add Missing Columns - 10 Jul 2025
 *
 * Purpose: Adds missing 'updated_at' column to batches table and 'last_error' 
 * column to trades table to complete the schema for full batch state management.
 *
 * @package FX-Trades-App
 * @author Assistant <assistant@example.com>
 *
 * Last 3 version commits:
 * @version 1.0 - INIT - 10 Jul 2025 - Initial commit of missing columns migration.
 */

return function( PDO $pdo ): void {
  // Helper function to check if a column exists
  $columnExists = function( PDO $pdo, string $table, string $column ): bool {
    $stmt = $pdo->prepare( "PRAGMA table_info($table)" );
    $stmt->execute();
    $columns = $stmt->fetchAll( PDO::FETCH_COLUMN, 1 );
    return in_array( $column, $columns );
  };

  // Add updated_at column to batches table if it doesn't exist
  if ( ! $columnExists( $pdo, 'batches', 'updated_at' ) )
  {
    $pdo->exec( "ALTER TABLE batches ADD COLUMN updated_at TEXT NOT NULL DEFAULT (datetime('now'))" );
    
    // Update existing records to have updated_at = created_at
    $pdo->exec( "UPDATE batches SET updated_at = created_at WHERE updated_at IS NULL" );
  }

  // Add last_error column to trades table if it doesn't exist
  if ( ! $columnExists( $pdo, 'trades', 'last_error' ) )
  {
    $pdo->exec( "ALTER TABLE trades ADD COLUMN last_error TEXT" );
  }

  // Create trigger for batches table to auto-update updated_at
  $pdo->exec( "
    CREATE TRIGGER IF NOT EXISTS update_batches_updated_at
    AFTER UPDATE ON batches
    FOR EACH ROW
    BEGIN
      UPDATE batches SET updated_at = datetime('now') WHERE id = OLD.id;
    END;
  " );

}; // return function 