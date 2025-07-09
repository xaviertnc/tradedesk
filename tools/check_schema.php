<?php
/**
 * check_schema.php
 *
 * Schema Checker - 09 Jul 2025
 *
 * Purpose: Check database schema to see what columns exist.
 *
 * @package FX-Trades-App
 * @author Gemini <gemini@google.com>
 *
 * @version 1.0 - INIT - 09 Jul 2025 - Initial commit
 */

$db_file = __DIR__ . '/../data/tradedesk.db';
$pdo = new PDO( 'sqlite:' . $db_file );
$pdo->setAttribute( PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION );

echo "=== Database Schema Check ===\n\n";

// Check batches table
echo "Batches table columns:\n";
$stmt = $pdo->query( 'PRAGMA table_info(batches)' );
$columns = $stmt->fetchAll( PDO::FETCH_ASSOC );

foreach ( $columns as $col ) {
  echo "  - {$col['name']} ({$col['type']})" . ( $col['notnull'] ? ' NOT NULL' : '' ) . "\n";
}

echo "\nTrades table columns:\n";
$stmt = $pdo->query( 'PRAGMA table_info(trades)' );
$columns = $stmt->fetchAll( PDO::FETCH_ASSOC );

foreach ( $columns as $col ) {
  echo "  - {$col['name']} ({$col['type']})" . ( $col['notnull'] ? ' NOT NULL' : '' ) . "\n";
}

echo "\n=== Schema Check Complete ===\n"; 