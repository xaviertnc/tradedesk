<?php
/**
 * migrations/2025_07_10_01_add_batch_management_tables.php
 *
 * DB Migration: Add Batch Management Tables - 09 Jul 2025
 *
 * Purpose: Creates the 'batches' table and adds 'batch_id' and 'last_error'
 * columns to the 'trades' table to support robust batch processing.
 *
 * @package FX-Trades-App
 * @author Gemini <gemini@google.com>
 *
 * Last 3 version commits:
 * @version 1.0 - INIT - 09 Jul 2025 - Initial commit of batch management migration.
 */

return function(PDO $pdo) {
  // Create the new 'batches' table to manage batch jobs.
  $pdo->exec( "
    CREATE TABLE IF NOT EXISTS batches (
      id INTEGER PRIMARY KEY AUTOINCREMENT,
      batch_uid TEXT NOT NULL UNIQUE,
      status TEXT NOT NULL DEFAULT 'PENDING',
      total_trades INTEGER NOT NULL DEFAULT 0,
      processed_trades INTEGER NOT NULL DEFAULT 0,
      failed_trades INTEGER NOT NULL DEFAULT 0,
      created_at TEXT NOT NULL DEFAULT (datetime('now')),
      updated_at TEXT NOT NULL DEFAULT (datetime('now'))
    )
  " );

  // Add a trigger to automatically update the 'updated_at' timestamp on the batches table.
  $pdo->exec( "
    CREATE TRIGGER IF NOT EXISTS update_batches_updated_at
    AFTER UPDATE ON batches
    FOR EACH ROW
    BEGIN
      UPDATE batches SET updated_at = datetime('now') WHERE id = OLD.id;
    END;
  " );

  // Add the 'batch_id' and 'last_error' columns to the existing 'trades' table if missing.
  $stmt = $pdo->query('PRAGMA table_info(trades)');
  $columns = $stmt->fetchAll(PDO::FETCH_COLUMN, 1);

  if (!in_array('batch_id', $columns)) {
    $pdo->exec("ALTER TABLE trades ADD COLUMN batch_id INTEGER REFERENCES batches(id)");
  }
  if (!in_array('last_error', $columns)) {
    $pdo->exec("ALTER TABLE trades ADD COLUMN last_error TEXT");
  }
};
