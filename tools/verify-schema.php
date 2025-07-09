<?php
/**
 * verify-schema.php
 *
 * CLI Schema Verification Utility - 28 Jun 2025 ( Start Date )
 *
 * Purpose: Command-line utility to verify database schema integrity.
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
  $db_file = 'data' . DIRECTORY_SEPARATOR . 'tradedesk.db';
  
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

// --- Main CLI Logic ---
function main() {
  echo "🔍 FX Trader Schema Verification Tool\n";
  echo "=====================================\n\n";
  
  $db = getDbConnection();
  $migrationService = new MigrationService( $db );
  
  echo "Verifying database schema...\n";
  $errors = $migrationService->verifySchema();
  
  $isValid = empty( $errors['missing_tables'] ) && empty( $errors['missing_columns'] );
  
  if ( $isValid ) {
    echo "✅ Schema validation PASSED\n";
    echo "All required tables and columns are present.\n";
    exit( 0 );
  } else {
    echo "❌ Schema validation FAILED\n\n";
    
    if ( !empty( $errors['missing_tables'] ) ) {
      echo "Missing tables:\n";
      foreach ( $errors['missing_tables'] as $table ) {
        echo "  - {$table}\n";
      }
      echo "\n";
    }
    
    if ( !empty( $errors['missing_columns'] ) ) {
      echo "Missing columns:\n";
      foreach ( $errors['missing_columns'] as $table => $columns ) {
        echo "  Table '{$table}':\n";
        foreach ( $columns as $column ) {
          echo "    - {$column}\n";
        }
      }
      echo "\n";
    }
    
    echo "💡 Recommended actions:\n";
    echo "  1. Run available migrations: php api.php?action=run_migration\n";
    echo "  2. If the problem persists, delete tradedesk.db to recreate the database\n";
    echo "  3. Check migration files for any issues\n";
    
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