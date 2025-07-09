<?php
/**
 * MigrationService.php
 *
 * FX Batch Trader - 28 Jun 2025 ( Start Date )
 *
 * Purpose: Handles database migrations and schema verification for FX Batch Trader.
 *
 * @package FXBatchTrader
 *
 * @author Your Name <email@domain.com>
 *
 * Last 3 version commits:
 * @version 1.0 - INIT - 28 Jun 2025 - Initial commit
 * @version x.x - FT|UPD - 29 Jun 2025 - Migrate spread to integer bips
 */
// php/services/MigrationService.php

class MigrationService {
  private PDO $db;
  private string $migrations_dir;
  private array $requiredSchema = [
    'config' => [ 'id', 'api_trading_url', 'api_account_url', 'auth_url', 'client_id', 'client_secret', 'username', 'password', 'api_external_token', 'otc_rate', 'access_token', 'token_expiry' ],
    'clients' => [ 'id', 'name', 'cif_number', 'zar_account', 'usd_account', 'spread' ],
    'bank_accounts' => [ 'id', 'cus_cif_no', 'cus_name', 'account_no', 'account_type', 'account_status', 'account_currency', 'curr_account_balance' ],
    'batches' => [ 'id', 'batch_uid', 'status', 'created_at' ],
    'trades' => [ 'id', 'client_id', 'batch_id', 'status', 'status_message', 'quote_id', 'quote_rate', 'amount_zar', 'bank_trxn_id', 'deal_ref', 'created_at' ],
    'migrations' => [ 'id', 'migration', 'ran_at' ]
  ];

  public function __construct( PDO $db ) {
    $this->db = $db;
    $this->migrations_dir = __DIR__ . '/../migrations';
    $this->ensureMigrationsTableExists();
  }

  private function ensureMigrationsTableExists(): void {
    $this->db->exec("CREATE TABLE IF NOT EXISTS migrations (
      id INTEGER PRIMARY KEY AUTOINCREMENT,
      migration TEXT NOT NULL UNIQUE,
      ran_at TEXT NOT NULL
    )");
  }

  public function getRanMigrations(): array {
    $stmt = $this->db->query("SELECT migration FROM migrations");
    return $stmt->fetchAll(PDO::FETCH_COLUMN, 0);
  }

  public function getAvailableMigrations(): array {
    if ( !is_dir($this->migrations_dir) ) {
      return [];
    }
    $allFiles = scandir($this->migrations_dir);
    $ranMigrations = $this->getRanMigrations();
    
    $available = [];
    foreach ( $allFiles as $file ) {
      if ( pathinfo($file, PATHINFO_EXTENSION) === 'php' ) {
        if ( !in_array($file, $ranMigrations) ) {
          $available[] = $file;
        }
      }
    }
    sort($available);
    return $available;
  }

  public function runMigration( string $filename ): void {
    $filepath = $this->migrations_dir . '/' . $filename;
    
    if ( !file_exists($filepath) ) {
      throw new Exception("Migration file not found: {$filename}");
    }

    $ranMigrations = $this->getRanMigrations();
    if ( in_array($filename, $ranMigrations) ) {
      throw new Exception("Migration already ran: {$filename}");
    }

    debug_log("Running migration: {$filename}");

    $migration_function = require $filepath;

    if ( !is_callable($migration_function) ) {
      throw new Exception("Migration file '{$filename}' must return a callable function.");
    }

    try {
      $this->db->beginTransaction();
      
      $migration_function($this->db);

      $stmt = $this->db->prepare("INSERT INTO migrations (migration, ran_at) VALUES (?, ?)");
      $stmt->execute([ $filename, date('Y-m-d H:i:s') ]);

      $this->db->commit();
      debug_log("Successfully ran and recorded migration: {$filename}");
    } catch ( Exception $e ) {
      $this->db->rollBack();
      debug_log("Failed to run migration '{$filename}': " . $e->getMessage(), 'MIGRATION_ERROR', 1, 'ERROR');
      throw $e;
    }
  }

  public function verifySchema(): array {
    $errors = [
      'missing_tables' => [],
      'missing_columns' => []
    ];

    $stmt = $this->db->query("SELECT name FROM sqlite_master WHERE type='table' AND name NOT LIKE 'sqlite_%'");
    $existingTables = $stmt->fetchAll(PDO::FETCH_COLUMN, 0);

    foreach ( $this->requiredSchema as $tableName => $requiredColumns ) {
      if ( !in_array($tableName, $existingTables) ) {
        $errors['missing_tables'][] = $tableName;
        continue;
      }

      $stmt = $this->db->query("PRAGMA table_info({$tableName})");
      $existingColumns = $stmt->fetchAll(PDO::FETCH_COLUMN, 1);

      $missing = array_diff($requiredColumns, $existingColumns);
      if ( !empty($missing) ) {
        $errors['missing_columns'][$tableName] = array_values($missing);
      }
    }

    return $errors;
  }
}
