<?php
/**
 * repair-database.php
 *
 * Database Repair Utility - 28 Jun 2025 ( Start Date )
 *
 * Purpose: Script to repair database schema issues by rerunning migrations or adding missing columns.
 *
 * @package FX Trader
 *
 * @author Assistant <assistant@example.com>
 *
 * Last 3 version commits:
 * @version 1.0 - INIT - 28 Jun 2025 - Initial commit
 */

// --- Error Reporting (for CLI) ---
ini_set( 'display_errors', 1 );
ini_set( 'display_startup_errors', 1 );
error_reporting( E_ALL );

// --- Include Services ---
require_once __DIR__ . '/MigrationService.php';

// --- Server-Side Logging ---
function debug_log( $var, $pretext = '', $minDebugLevel = 1, $type = 'DEBUG', $format = 'text' ) {
  $log_file = __DIR__ . '/debug.log';
  $timestamp = date( 'Y-m-d H:i:s' );
  $log_entry = "[$timestamp] [$type] $pretext: ";

  if ( is_string( $var ) || is_numeric( $var ) ) {
    $log_entry .= $var;
  } else {
    $log_entry .= print_r( $var, true );
  }

  file_put_contents( $log_file, $log_entry . PHP_EOL, FILE_APPEND );
}

// --- Database Setup ---
function getDbConnection() {
  $db_file = 'fx_trader.db';
  
  if ( !file_exists( $db_file ) ) {
    echo "ERROR: Database file '{$db_file}' not found.\n";
    exit( 1 );
  }
  
  try {
    $pdo = new PDO( 'sqlite:' . $db_file );
    $pdo->setAttribute( PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION );
    $pdo->setAttribute( PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC );
    return $pdo;
  } catch ( PDOException $e ) {
    echo "ERROR: Database connection failed: " . $e->getMessage() . "\n";
    exit( 1 );
  }
}

// --- Repair Functions ---
function addMissingColumns( PDO $db ): array {
  $repairs = [];
  
  // Check and add missing columns to trades table
  $columns = $db->query( "PRAGMA table_info(trades)" )->fetchAll( PDO::FETCH_COLUMN, 1 );
  
  $requiredColumns = [
    'batch_id' => 'INTEGER',
    'quote_id' => 'TEXT', 
    'quote_rate' => 'REAL',
    'deal_ref' => 'TEXT'
  ];
  
  foreach ( $requiredColumns as $column => $type ) {
    if ( !in_array( $column, $columns ) ) {
      try {
        $db->exec( "ALTER TABLE trades ADD COLUMN {$column} {$type}" );
        $repairs[] = "Added column '{$column}' to trades table";
        echo "✅ Added column '{$column}' to trades table\n";
      } catch ( Exception $e ) {
        echo "❌ Failed to add column '{$column}': " . $e->getMessage() . "\n";
      }
    }
  }
  
  return $repairs;
}

function rerunMigrations( PDO $db ): array {
  $repairs = [];
  $migrationService = new MigrationService( $db );
  
  // Get available migrations
  $available = $migrationService->getAvailableMigrations();
  
  if ( empty( $available ) ) {
    echo "ℹ️  No pending migrations to run\n";
    return $repairs;
  }
  
  echo "🔄 Running pending migrations...\n";
  
  foreach ( $available as $migration ) {
    try {
      $migrationService->runMigration( $migration );
      $repairs[] = "Ran migration: {$migration}";
      echo "✅ Ran migration: {$migration}\n";
    } catch ( Exception $e ) {
      echo "❌ Failed to run migration '{$migration}': " . $e->getMessage() . "\n";
    }
  }
  
  return $repairs;
}

function createMissingTables( PDO $db ): array {
  $repairs = [];
  
  // Get existing tables
  $existingTables = $db->query( "SELECT name FROM sqlite_master WHERE type='table' AND name NOT LIKE 'sqlite_%'" )->fetchAll( PDO::FETCH_COLUMN, 0 );
  
  // Define required tables and their schemas
  $requiredTables = [
    'config' => "CREATE TABLE config (
      id INTEGER PRIMARY KEY,
      api_trading_url TEXT,
      api_account_url TEXT,
      auth_url TEXT,
      client_id TEXT,
      client_secret TEXT,
      username TEXT,
      password TEXT,
      api_external_token TEXT,
      otc_rate REAL,
      access_token TEXT,
      token_expiry INTEGER
    )",
    'clients' => "CREATE TABLE clients (
      id INTEGER PRIMARY KEY AUTOINCREMENT,
      name TEXT NOT NULL,
      cif_number TEXT NOT NULL UNIQUE,
      zar_account TEXT,
      usd_account TEXT,
      spread REAL NOT NULL
    )",
    'bank_accounts' => "CREATE TABLE bank_accounts (
      id INTEGER PRIMARY KEY AUTOINCREMENT,
      cus_cif_no TEXT NOT NULL,
      cus_name TEXT,
      account_no TEXT NOT NULL,
      account_type TEXT,
      account_status TEXT,
      account_currency TEXT,
      curr_account_balance REAL,
      UNIQUE(account_no)
    )",
    'batches' => "CREATE TABLE batches (
      id INTEGER PRIMARY KEY AUTOINCREMENT,
      batch_uid TEXT NOT NULL UNIQUE,
      status TEXT NOT NULL,
      created_at TEXT NOT NULL
    )",
    'trades' => "CREATE TABLE trades (
      id INTEGER PRIMARY KEY AUTOINCREMENT,
      client_id INTEGER,
      batch_id INTEGER,
      status TEXT,
      status_message TEXT,
      quote_id TEXT,
      quote_rate REAL,
      amount_zar REAL,
      bank_trxn_id TEXT,
      deal_ref TEXT,
      created_at TEXT,
      FOREIGN KEY (client_id) REFERENCES clients(id),
      FOREIGN KEY (batch_id) REFERENCES batches(id)
    )",
    'migrations' => "CREATE TABLE migrations (
      id INTEGER PRIMARY KEY AUTOINCREMENT,
      migration TEXT NOT NULL UNIQUE,
      ran_at TEXT NOT NULL
    )"
  ];
  
  foreach ( $requiredTables as $table => $sql ) {
    if ( !in_array( $table, $existingTables ) ) {
      try {
        $db->exec( $sql );
        $repairs[] = "Created table: {$table}";
        echo "✅ Created table: {$table}\n";
      } catch ( Exception $e ) {
        echo "❌ Failed to create table '{$table}': " . $e->getMessage() . "\n";
      }
    }
  }
  
  return $repairs;
}

// --- Main Repair Logic ---
function main() {
  echo "🔧 FX Trader Database Repair Tool\n";
  echo "=================================\n\n";
  
  $db = getDbConnection();
  $migrationService = new MigrationService( $db );
  
  echo "🔍 Analyzing database schema...\n";
  $errors = $migrationService->verifySchema();
  $isValid = empty( $errors['missing_tables'] ) && empty( $errors['missing_columns'] );
  
  if ( $isValid ) {
    echo "✅ Database schema is already valid. No repairs needed.\n";
    exit( 0 );
  }
  
  echo "❌ Schema issues detected. Starting repairs...\n\n";
  
  $allRepairs = [];
  
  // Step 1: Create missing tables
  if ( !empty( $errors['missing_tables'] ) ) {
    echo "📋 Creating missing tables...\n";
    $allRepairs = array_merge( $allRepairs, createMissingTables( $db ) );
  }
  
  // Step 2: Add missing columns
  if ( !empty( $errors['missing_columns'] ) ) {
    echo "📋 Adding missing columns...\n";
    $allRepairs = array_merge( $allRepairs, addMissingColumns( $db ) );
  }
  
  // Step 3: Run pending migrations
  echo "📋 Running pending migrations...\n";
  $allRepairs = array_merge( $allRepairs, rerunMigrations( $db ) );
  
  // Step 4: Verify repairs
  echo "\n🔍 Verifying repairs...\n";
  $errors = $migrationService->verifySchema();
  $isValid = empty( $errors['missing_tables'] ) && empty( $errors['missing_columns'] );
  
  if ( $isValid ) {
    echo "✅ Database repair completed successfully!\n";
    echo "Repairs performed:\n";
    foreach ( $allRepairs as $repair ) {
      echo "  - {$repair}\n";
    }
    exit( 0 );
  } else {
    echo "❌ Database repair incomplete. Remaining issues:\n";
    if ( !empty( $errors['missing_tables'] ) ) {
      echo "  Missing tables: " . implode( ', ', $errors['missing_tables'] ) . "\n";
    }
    if ( !empty( $errors['missing_columns'] ) ) {
      foreach ( $errors['missing_columns'] as $table => $columns ) {
        echo "  Table '{$table}' missing columns: " . implode( ', ', $columns ) . "\n";
      }
    }
    echo "\n💡 Consider deleting fx_trader.db to recreate the database from scratch.\n";
    exit( 1 );
  }
}

// --- CLI Entry Point ---
if ( php_sapi_name() === 'cli' ) {
  main();
} else {
  echo "This script is intended for command-line use only.\n";
  exit( 1 );
} 