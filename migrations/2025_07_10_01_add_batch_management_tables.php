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

class AddBatchManagementTables
{
  /**
   * Run the migrations.
   *
   * @param PDO $pdo
   * @return void
   */
  public function up( PDO $pdo ): void
  {
    // Create the new 'batches' table to manage batch jobs.
    // - status: PENDING, PROCESSING, COMPLETED, FAILED
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

    // Add the 'batch_id' and 'last_error' columns to the existing 'trades' table.
    // We check if the columns exist before adding them to make the migration re-runnable.
    if ( ! $this->columnExists( $pdo, 'trades', 'batch_id' ) )
    {
      $pdo->exec( "ALTER TABLE trades ADD COLUMN batch_id INTEGER REFERENCES batches(id)" );
    }

    if ( ! $this->columnExists( $pdo, 'trades', 'last_error' ) )
    {
      $pdo->exec( "ALTER TABLE trades ADD COLUMN last_error TEXT" );
    }
  } // up


  /**
   * Reverse the migrations.
   *
   * @param PDO $pdo
   * @return void
   */
  public function down( PDO $pdo ): void
  {
    // Drop the trigger first
    $pdo->exec( "DROP TRIGGER IF EXISTS update_batches_updated_at" );

    // Drop the batches table
    $pdo->exec( "DROP TABLE IF EXISTS batches" );

    // SQLite doesn't support DROP COLUMN directly.
    // The process is to create a new table without the columns, copy the data,
    // drop the old table, and rename the new one.
    $pdo->exec( "PRAGMA foreign_keys=off;" );
    $pdo->exec( "BEGIN TRANSACTION;" );

    // Create a new table 'trades_new' with the original schema
    $pdo->exec( "
      CREATE TABLE trades_new (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        source_currency TEXT,
        source_amount REAL,
        destination_currency TEXT,
        destination_amount REAL,
        status TEXT,
        trade_date TEXT,
        value_date TEXT,
        created_at TEXT,
        updated_at TEXT
        -- The columns 'batch_id' and 'last_error' are omitted
      )
    " );

    // Copy data from the old 'trades' table to 'trades_new'
    $pdo->exec( "
      INSERT INTO trades_new (id, source_currency, source_amount, destination_currency, destination_amount, status, trade_date, value_date, created_at, updated_at)
      SELECT id, source_currency, source_amount, destination_currency, destination_amount, status, trade_date, value_date, created_at, updated_at FROM trades
    " );

    // Drop the old 'trades' table
    $pdo->exec( "DROP TABLE trades" );

    // Rename 'trades_new' to 'trades'
    $pdo->exec( "ALTER TABLE trades_new RENAME TO trades" );

    $pdo->exec( "COMMIT;" );
    $pdo->exec( "PRAGMA foreign_keys=on;" );
  } // down


  /**
   * Check if a column exists in a table.
   *
   * @param PDO $pdo
   * @param string $table
   * @param string $column
   * @return bool
   */
  private function columnExists( PDO $pdo, string $table, string $column ): bool
  {
    $stmt = $pdo->prepare( "PRAGMA table_info($table)" );
    $stmt->execute();
    $columns = $stmt->fetchAll( PDO::FETCH_COLUMN, 1 );
    return in_array( $column, $columns );
  } // columnExists

} // AddBatchManagementTables
