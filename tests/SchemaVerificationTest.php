<?php
/**
 * tests/SchemaVerificationTest.php
 *
 * Schema Verification Unit Tests - 28 Jun 2025 ( Start Date )
 *
 * Purpose: Unit tests for database schema verification functionality.
 *
 * @package FX Trader
 *
 * @author Assistant <assistant@example.com>
 *
 * Last 3 version commits:
 * @version 1.0 - INIT - 28 Jun 2025 - Initial commit
 */

require_once __DIR__ . '/../MigrationService.php';

class SchemaVerificationTest {
  private PDO $db;
  private MigrationService $migrationService;
  
  public function __construct() {
    // Create in-memory SQLite database for testing
    $this->db = new PDO( 'sqlite::memory:' );
    $this->db->setAttribute( PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION );
    $this->db->setAttribute( PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC );
    
    $this->migrationService = new MigrationService( $this->db );
  }
  
  private function setupValidSchema(): void {
    // Clear any existing tables first
    $this->db->exec( "DROP TABLE IF EXISTS migrations" );
    $this->db->exec( "DROP TABLE IF EXISTS trades" );
    $this->db->exec( "DROP TABLE IF EXISTS batches" );
    $this->db->exec( "DROP TABLE IF EXISTS clients" );
    $this->db->exec( "DROP TABLE IF EXISTS bank_accounts" );
    $this->db->exec( "DROP TABLE IF EXISTS config" );
    
    // Create all required tables with correct schema
    $this->db->exec( "CREATE TABLE config (
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
    )" );
    
    $this->db->exec( "CREATE TABLE clients (
      id INTEGER PRIMARY KEY AUTOINCREMENT,
      name TEXT NOT NULL,
      cif_number TEXT NOT NULL UNIQUE,
      zar_account TEXT,
      usd_account TEXT,
      spread INTEGER NOT NULL
    )" );
    
    $this->db->exec( "CREATE TABLE bank_accounts (
      id INTEGER PRIMARY KEY AUTOINCREMENT,
      cus_cif_no TEXT NOT NULL,
      cus_name TEXT,
      account_no TEXT NOT NULL,
      account_type TEXT,
      account_status TEXT,
      account_currency TEXT,
      curr_account_balance REAL,
      UNIQUE(account_no)
    )" );
    
    $this->db->exec( "CREATE TABLE batches (
      id INTEGER PRIMARY KEY AUTOINCREMENT,
      batch_uid TEXT NOT NULL UNIQUE,
      status TEXT NOT NULL,
      created_at TEXT NOT NULL
    )" );
    
    $this->db->exec( "CREATE TABLE trades (
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
    )" );
    
    $this->db->exec( "CREATE TABLE migrations (
      id INTEGER PRIMARY KEY AUTOINCREMENT,
      migration TEXT NOT NULL UNIQUE,
      ran_at TEXT NOT NULL
    )" );
  }
  
  public function testValidSchema(): void {
    echo "🧪 Testing valid schema...\n";
    $this->setupValidSchema();
    
    $errors = $this->migrationService->verifySchema();
    $isValid = empty( $errors['missing_tables'] ) && empty( $errors['missing_columns'] );
    
    if ( $isValid ) {
      echo "✅ Valid schema test PASSED\n";
    } else {
      echo "❌ Valid schema test FAILED\n";
      echo "Errors: " . print_r( $errors, true ) . "\n";
      throw new Exception( "Valid schema should not have errors" );
    }
  }
  
  public function testMissingTable(): void {
    echo "🧪 Testing missing table detection...\n";
    $this->setupValidSchema();
    
    // Drop a required table
    $this->db->exec( "DROP TABLE trades" );
    
    $errors = $this->migrationService->verifySchema();
    
    if ( in_array( 'trades', $errors['missing_tables'] ) ) {
      echo "✅ Missing table test PASSED\n";
    } else {
      echo "❌ Missing table test FAILED\n";
      echo "Expected 'trades' in missing_tables, got: " . print_r( $errors['missing_tables'], true ) . "\n";
      throw new Exception( "Should detect missing 'trades' table" );
    }
  }
  
  public function testMissingColumn(): void {
    echo "🧪 Testing missing column detection...\n";
    $this->setupValidSchema();
    
    // Drop a required column from trades table
    $this->db->exec( "CREATE TABLE trades_backup AS SELECT id, client_id, status, status_message, quote_id, quote_rate, amount_zar, bank_trxn_id, deal_ref, created_at FROM trades" );
    $this->db->exec( "DROP TABLE trades" );
    $this->db->exec( "ALTER TABLE trades_backup RENAME TO trades" );
    
    $errors = $this->migrationService->verifySchema();
    
    if ( isset( $errors['missing_columns']['trades'] ) && in_array( 'batch_id', $errors['missing_columns']['trades'] ) ) {
      echo "✅ Missing column test PASSED\n";
    } else {
      echo "❌ Missing column test FAILED\n";
      echo "Expected 'batch_id' in missing_columns['trades'], got: " . print_r( $errors['missing_columns'], true ) . "\n";
      throw new Exception( "Should detect missing 'batch_id' column in 'trades' table" );
    }
  }
  
  public function testMultipleIssues(): void {
    echo "🧪 Testing multiple issues detection...\n";
    $this->setupValidSchema();
    
    // Drop multiple tables and columns
    $this->db->exec( "DROP TABLE trades" );
    $this->db->exec( "DROP TABLE batches" );
    $this->db->exec( "ALTER TABLE clients DROP COLUMN zar_account" );
    
    $errors = $this->migrationService->verifySchema();
    
    $expectedTables = [ 'trades', 'batches' ];
    $expectedColumns = [ 'zar_account' ];
    
    $tablesCorrect = count( array_intersect( $expectedTables, $errors['missing_tables'] ) ) === count( $expectedTables );
    $columnsCorrect = isset( $errors['missing_columns']['clients'] ) && 
                     count( array_intersect( $expectedColumns, $errors['missing_columns']['clients'] ) ) === count( $expectedColumns );
    
    if ( $tablesCorrect && $columnsCorrect ) {
      echo "✅ Multiple issues test PASSED\n";
    } else {
      echo "❌ Multiple issues test FAILED\n";
      echo "Expected missing tables: " . implode( ', ', $expectedTables ) . "\n";
      echo "Got missing tables: " . implode( ', ', $errors['missing_tables'] ) . "\n";
      echo "Expected missing columns in 'clients': " . implode( ', ', $expectedColumns ) . "\n";
      echo "Got missing columns: " . print_r( $errors['missing_columns'], true ) . "\n";
      throw new Exception( "Should detect multiple missing tables and columns" );
    }
  }
  
  public function runAllTests(): void {
    echo "🚀 Running Schema Verification Tests\n";
    echo "===================================\n\n";
    
    try {
      $this->testValidSchema();
      $this->testMissingTable();
      $this->testMissingColumn();
      $this->testMultipleIssues();
      
      echo "\n🎉 All tests PASSED!\n";
      exit( 0 );
    } catch ( Exception $e ) {
      echo "\n💥 Test FAILED: " . $e->getMessage() . "\n";
      exit( 1 );
    }
  }
}

// --- CLI Entry Point ---
if ( php_sapi_name() === 'cli' ) {
  $test = new SchemaVerificationTest();
  $test->runAllTests();
} else {
  echo "This script is intended for command-line use only.\n";
  exit( 1 );
} 