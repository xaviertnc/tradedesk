<?php
/**
 * migrations/2025_07_10_03_add_missing_updated_at.php
 *
 * DB Migration: Add Missing Updated At Column - 09 Jul 2025
 *
 * Purpose: Adds the missing 'updated_at' column to the 'batches' table.
 *
 * @package FX-Trades-App
 * @author Gemini <gemini@google.com>
 *
 * @version 1.1 - FIX - 09 Jul 2025 - Use function format for MigrationService
 */

return function( PDO $pdo ) {
  // Add the missing 'updated_at' column to the batches table
  $stmt = $pdo->query( "PRAGMA table_info(batches)" );
  $columns = $stmt->fetchAll( PDO::FETCH_COLUMN, 1 );
  if ( !in_array( 'updated_at', $columns ) ) {
    $pdo->exec( "ALTER TABLE batches ADD COLUMN updated_at TEXT NOT NULL DEFAULT (datetime('now'))" );
  }

  // Add a trigger to automatically update the 'updated_at' timestamp
  $pdo->exec( "
    CREATE TRIGGER IF NOT EXISTS update_batches_updated_at
    AFTER UPDATE ON batches
    FOR EACH ROW
    BEGIN
      UPDATE batches SET updated_at = datetime('now') WHERE id = OLD.id;
    END;
  " );
}; 