<?php
try {
  $db_file = 'data' . DIRECTORY_SEPARATOR . 'tradedesk.db';
  $pdo = new PDO( 'sqlite:' . $db_file );
  $pdo->setAttribute( PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION );
  
  echo "Batches table columns:\n";
  $stmt = $pdo->query( 'PRAGMA table_info(batches)' );
  $columns = $stmt->fetchAll( PDO::FETCH_ASSOC );
  foreach ( $columns as $col ) {
    echo "- {$col['name']} ({$col['type']})\n";
  }
  
  echo "\nTrades table columns:\n";
  $stmt = $pdo->query( 'PRAGMA table_info(trades)' );
  $columns = $stmt->fetchAll( PDO::FETCH_ASSOC );
  foreach ( $columns as $col ) {
    echo "- {$col['name']} ({$col['type']})\n";
  }
  
} catch ( Exception $e ) {
  echo "Error: " . $e->getMessage() . "\n";
} 