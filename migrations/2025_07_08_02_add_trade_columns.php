<?php
// php/migrations/2025_07_08_02_add_trade_columns.php

return function( PDO $db ) {
  debug_log('Starting migration to add new columns to the trades table...');

  $db->beginTransaction();
  try {
    // SQLite doesn't support ADD COLUMN IF NOT EXISTS directly.
    // We must check for the column's existence first.
    $columns = $db->query("PRAGMA table_info(trades)")->fetchAll(PDO::FETCH_COLUMN, 1);

    if ( !in_array('batch_id', $columns) ) {
      $db->exec("ALTER TABLE trades ADD COLUMN batch_id INTEGER");
      debug_log('Added column: batch_id');
    }
    if ( !in_array('quote_id', $columns) ) {
      $db->exec("ALTER TABLE trades ADD COLUMN quote_id TEXT");
      debug_log('Added column: quote_id');
    }
    if ( !in_array('quote_rate', $columns) ) {
      $db->exec("ALTER TABLE trades ADD COLUMN quote_rate REAL");
      debug_log('Added column: quote_rate');
    }
    if ( !in_array('deal_ref', $columns) ) {
      $db->exec("ALTER TABLE trades ADD COLUMN deal_ref TEXT");
      debug_log('Added column: deal_ref');
    }
    
    $db->commit();
    debug_log('Trade columns migration finished successfully.');

  } catch ( Exception $e ) {
    $db->rollBack();
    debug_log('Migration failed for trades table, rolling back. Error: ' . $e->getMessage(), 'MIGRATION_ERROR', 1, 'ERROR');
    throw $e;
  }
};
