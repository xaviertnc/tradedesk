<?php
/**
 * tools/add_updated_at_column.php
 *
 * Add Updated At Column - 09 Jul 2025
 *
 * Purpose: Directly adds the missing 'updated_at' column to the 'batches' table.
 *
 * @package FX-Trades-App
 * @author Gemini <gemini@google.com>
 *
 * @version 1.0 - INIT - 09 Jul 2025 - Initial commit
 */

try {
  $db_file = __DIR__ . '/../data/tradedesk.db';
  $pdo = new PDO( 'sqlite:' . $db_file );
  $pdo->setAttribute( PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION );
  
  echo "=== Adding updated_at column to batches table ===\n";
  
  // Check if column already exists
  $stmt = $pdo->query( "PRAGMA table_info(batches)" );
  $columns = $stmt->fetchAll( PDO::FETCH_COLUMN, 1 );
  
  if ( in_array( 'updated_at', $columns ) ) {
    echo "✅ Column 'updated_at' already exists in batches table\n";
  } else {
    // Add the missing 'updated_at' column to the batches table
    $pdo->exec( "ALTER TABLE batches ADD COLUMN updated_at TEXT" );
    echo "✅ Added 'updated_at' column to batches table\n";
    
    // Update existing records with current timestamp
    $pdo->exec( "UPDATE batches SET updated_at = datetime('now') WHERE updated_at IS NULL" );
    echo "✅ Updated existing records with current timestamp\n";
    
    // Add a trigger to automatically update the 'updated_at' timestamp
    $pdo->exec( "
      CREATE TRIGGER IF NOT EXISTS update_batches_updated_at
      AFTER UPDATE ON batches
      FOR EACH ROW
      BEGIN
        UPDATE batches SET updated_at = datetime('now') WHERE id = OLD.id;
      END;
    " );
    echo "✅ Created trigger to auto-update 'updated_at' timestamp\n";
  }
  
  // Verify the column was added
  $stmt = $pdo->query( "PRAGMA table_info(batches)" );
  $columns = $stmt->fetchAll( PDO::FETCH_COLUMN, 1 );
  
  echo "\n=== Current batches table columns ===\n";
  foreach ( $columns as $column ) {
    echo "  - {$column}\n";
  }
  
  echo "\n✅ Database schema updated successfully!\n";
  
} catch ( Exception $e ) {
  echo "❌ Error: " . $e->getMessage() . "\n";
  exit( 1 );
} 