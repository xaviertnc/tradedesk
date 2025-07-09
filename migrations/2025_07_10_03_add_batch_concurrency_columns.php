<?php
/**
 * migrations/2025_07_10_03_add_batch_concurrency_columns.php
 *
 * DB Migration: Add Batch Concurrency Management - 10 Jul 2025
 *
 * Purpose: Adds columns to the 'batches' table to support concurrent batch
 * processing, locking mechanisms, queue management, and priority handling.
 *
 * @package FX-Trades-App
 * @author Gemini <gemini@google.com>
 *
 * Last 3 version commits:
 * @version 1.0 - INIT - 10 Jul 2025 - Initial commit of batch concurrency migration.
 */

return function(PDO $pdo) {
  // Add concurrency management columns to batches table
  $stmt = $pdo->query('PRAGMA table_info(batches)');
  $columns = $stmt->fetchAll(PDO::FETCH_COLUMN, 1);

  // Add lock-related columns
  if (!in_array('locked_at', $columns)) {
    $pdo->exec("ALTER TABLE batches ADD COLUMN locked_at TEXT");
  }
  if (!in_array('locked_by', $columns)) {
    $pdo->exec("ALTER TABLE batches ADD COLUMN locked_by TEXT");
  }
  if (!in_array('lock_timeout', $columns)) {
    $pdo->exec("ALTER TABLE batches ADD COLUMN lock_timeout TEXT");
  }

  // Add queue management columns
  if (!in_array('priority', $columns)) {
    $pdo->exec("ALTER TABLE batches ADD COLUMN priority INTEGER DEFAULT 5");
  }
  if (!in_array('queue_position', $columns)) {
    $pdo->exec("ALTER TABLE batches ADD COLUMN queue_position INTEGER DEFAULT 0");
  }
  if (!in_array('started_at', $columns)) {
    $pdo->exec("ALTER TABLE batches ADD COLUMN started_at TEXT");
  }
  if (!in_array('completed_at', $columns)) {
    $pdo->exec("ALTER TABLE batches ADD COLUMN completed_at TEXT");
  }

  // Add concurrency control columns
  if (!in_array('max_concurrent_trades', $columns)) {
    $pdo->exec("ALTER TABLE batches ADD COLUMN max_concurrent_trades INTEGER DEFAULT 5");
  }
  if (!in_array('current_concurrent_trades', $columns)) {
    $pdo->exec("ALTER TABLE batches ADD COLUMN current_concurrent_trades INTEGER DEFAULT 0");
  }

  // Create index for queue management
  $pdo->exec("
    CREATE INDEX IF NOT EXISTS idx_batches_queue 
    ON batches (priority DESC, queue_position ASC, created_at ASC)
  ");

  // Create index for lock management
  $pdo->exec("
    CREATE INDEX IF NOT EXISTS idx_batches_lock 
    ON batches (locked_at, lock_timeout)
  ");

  // Create index for active batches
  $pdo->exec("
    CREATE INDEX IF NOT EXISTS idx_batches_active 
    ON batches (status, started_at, completed_at)
  ");
}; 