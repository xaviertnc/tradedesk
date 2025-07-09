<?php

/**
 * migrations/2025_07_10_04_add_batch_notifications_table.php
 *
 * Add Batch Notifications Table - 10 Jul 2025 ( Start Date )
 *
 * Purpose: Creates batch_notifications table for storing real-time notifications
 *          and completion alerts for batch processing.
 *
 * @package TradeDesk Migrations
 *
 * @author Assistant <assistant@example.com>
 *
 * Last 3 version commits:
 * @version 1.0 - INIT - 10 Jul 2025 - Initial commit
 */

return function( $db ) {
  // Create batch_notifications table
  $sql = "CREATE TABLE IF NOT EXISTS batch_notifications (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    batch_id INTEGER NOT NULL,
    type VARCHAR(50) NOT NULL DEFAULT 'completion',
    data TEXT NOT NULL,
    sent_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (batch_id) REFERENCES batches(id) ON DELETE CASCADE
  )";
  
  $db->exec( $sql );
  
  // Create indexes for performance
  $db->exec( "CREATE INDEX IF NOT EXISTS idx_batch_notifications_batch_id ON batch_notifications(batch_id)" );
  $db->exec( "CREATE INDEX IF NOT EXISTS idx_batch_notifications_type ON batch_notifications(type)" );
  $db->exec( "CREATE INDEX IF NOT EXISTS idx_batch_notifications_sent_at ON batch_notifications(sent_at)" );
  $db->exec( "CREATE INDEX IF NOT EXISTS idx_batch_notifications_created_at ON batch_notifications(created_at)" );
  
  return true;
}; 