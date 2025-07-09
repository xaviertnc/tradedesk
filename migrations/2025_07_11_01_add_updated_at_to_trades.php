<?php
/**
 * migrations/2025_07_11_01_add_updated_at_to_trades.php
 *
 * Add updated_at column to trades table - 11 Jul 2025
 *
 * @package TradeDesk Migrations
 * @author Assistant <assistant@example.com>
 * @version 1.0 - INIT - 11 Jul 2025 - Initial commit
 */
return function( $db ) {
  $db->exec("ALTER TABLE trades ADD COLUMN updated_at TEXT");
  // Backfill with created_at for existing rows
  $db->exec("UPDATE trades SET updated_at = created_at WHERE updated_at IS NULL OR updated_at = ''");
  return true;
}; 