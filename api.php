<?php
/**
 * api.php
 *
 * FX Batch Trader API - 09 Jul 2025
 *
 * Purpose: Main API controller for FX Batch Trader, handling all CRUD and batch trading actions.
 *
 * @package FXBatchTrader
 *
 * @author Xavier TNC <xavier@tnc.com>
 * @author Gemini <gemini@google.com>
 *
 * Last 3 version commits:
 * @version 1.1 - FEAT - 09 Jul 2025 - Add CSV batch import and enhance batches table.
 * @version 1.0 - INIT - 28 Jun 2025 - Initial commit
 */
// api.php - Main backend handler

// --- Error Reporting (for development) ---
ini_set( 'display_errors', 1 );
ini_set( 'display_startup_errors', 1 );
error_reporting( E_ALL );

// --- Include Services ---
require_once __DIR__ . '/HttpClientService.php';
require_once __DIR__ . '/CapitecApiService.php';
require_once __DIR__ . '/MigrationService.php';
require_once __DIR__ . '/GeminiApiService.php';

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

// --- Response Header ---
header( 'Content-Type: application/json' );

// --- Database Setup ---
function getDbConnection() {
  $db_file = 'fx_trader.db';
  
  try {
    $pdo = new PDO( 'sqlite:' . $db_file );
    $pdo->setAttribute( PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION );
    $pdo->setAttribute( PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC );
  } catch ( PDOException $e ) {
    http_response_code( 500 );
    echo json_encode( [ 'success' => false, 'message' => 'Database connection failed: ' . $e->getMessage() ] );
    exit;
  }

  debug_log( 'Ensuring all database tables exist...' );
  
  // Config Table
  $pdo->exec( "CREATE TABLE IF NOT EXISTS config (
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
  $pdo->exec( "INSERT OR IGNORE INTO config (id, api_external_token) VALUES (1, 'YOUR_INTERMEDIATE_TOKEN')" );

  // Clients Table
  $pdo->exec( "CREATE TABLE IF NOT EXISTS clients (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    name TEXT NOT NULL,
    cif_number TEXT NOT NULL UNIQUE,
    zar_account TEXT,
    usd_account TEXT,
    spread INTEGER NOT NULL
  )" );

  // Bank Accounts Table
  $pdo->exec( "CREATE TABLE IF NOT EXISTS bank_accounts (
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
  
  // Batches Table - Enhanced for state tracking
  $pdo->exec( "CREATE TABLE IF NOT EXISTS batches (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    batch_uid TEXT NOT NULL UNIQUE,
    status TEXT NOT NULL,
    total_trades INTEGER NOT NULL DEFAULT 0,
    processed_trades INTEGER NOT NULL DEFAULT 0,
    failed_trades INTEGER NOT NULL DEFAULT 0,
    created_at TEXT NOT NULL
  )" );

  $pdo->exec( "CREATE TABLE IF NOT EXISTS trades (
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
  
  debug_log( 'Database schema verified.' );
  return $pdo;
}

// --- Main Router ---
$action = $_GET['action'] ?? '';
$db = getDbConnection();

try {
  debug_log( "Processing action: '{$action}'" );
  switch ( $action ) {
    case 'get_config': handleGetConfig( $db ); break;
    case 'save_config': handleSaveConfig( $db ); break;
    case 'get_clients': handleGetClients( $db ); break;
    case 'save_client': handleSaveClient( $db ); break;
    case 'delete_client': handleDeleteClient( $db ); break;
    case 'import_clients': handleImportClients( $db ); break;
    case 'find_zar_account': handleFindZarAccount( $db ); break;
    case 'sync_bank_accounts': handleSyncBankAccounts( $db ); break;
    case 'get_bank_accounts': handleGetBankAccounts( $db ); break;
    case 'get_migrations': handleGetMigrations( $db ); break;
    case 'run_migration': handleRunMigration( $db ); break;
    case 'stage_batch': handleStageBatch( $db ); break;
    case 'import_trades_batch': handleImportTradesBatch( $db ); break; // New Action
    case 'get_market_analysis': handleGetMarketAnalysis( $db ); break;
    case 'verify_schema': handleVerifySchema( $db ); break;
    default:
      http_response_code( 404 );
      echo json_encode( [ 'success' => false, 'message' => 'Action not found' ] );
  }
} catch ( Exception $e ) {
  http_response_code( 500 );
  debug_log( "Caught exception in api.php: " . $e->getMessage(), 'FATAL', 1, 'ERROR' );
  echo json_encode( [ 'success' => false, 'message' => 'An internal server error occurred: ' . $e->getMessage() ] );
}

// --- Handler Functions ---

function handleGetConfig( $db ) {
  $stmt = $db->query( "SELECT * FROM config WHERE id = 1" );
  $config = $stmt->fetch();
  echo json_encode( $config ?: (object)[] );
}

function handleSaveConfig( $db ) {
  $fields = [ 'api_trading_url', 'api_account_url', 'auth_url', 'client_id', 'client_secret', 'username', 'password', 'api_external_token', 'otc_rate' ];
  $updates = [];
  $params = [];
  foreach ( $fields as $field ) {
    if ( isset( $_POST[$field] ) ) {
      $updates[] = "$field = :$field";
      $params[":$field"] = $_POST[$field];
    }
  }
  
  if ( empty( $updates ) ) {
    echo json_encode( [ 'success' => false, 'message' => 'No data provided' ] );
    return;
  }

  $params[':id'] = 1;
  $sql = "UPDATE config SET " . implode( ', ', $updates ) . " WHERE id = :id";
  $stmt = $db->prepare( $sql );

  if ( $stmt->execute( $params ) ) {
    echo json_encode( [ 'success' => true ] );
  } else {
    throw new Exception( "Failed to save settings." );
  }
}

function handleGetClients( $db ) {
  $stmt = $db->query( "SELECT * FROM clients ORDER BY name ASC" );
  $clients = $stmt->fetchAll();
  echo json_encode( $clients );
}

function handleSaveClient( $db ) {
  $id = $_POST['id'] ?? null;
  $name = $_POST['client-name'] ?? '';
  $cif = $_POST['client-cif'] ?? '';
  $zar = $_POST['client-zar'] ?? '';
  $usd = $_POST['client-usd'] ?? '';
  $spread = isset($_POST['client-spread']) ? (int)$_POST['client-spread'] : 0;

  // Ensure spread is integer (bips)
  if ( empty( $name ) || empty( $cif ) || $spread < 0 ) {
    http_response_code( 400 );
    echo json_encode( [ 'success' => false, 'message' => 'Invalid data provided. Spread must be a non-negative integer (bips).' ] );
    return;
  }

  try {
    if ( $id ) { // Update
      $sql = "UPDATE clients SET name = :name, cif_number = :cif, zar_account = :zar, usd_account = :usd, spread = :spread WHERE id = :id";
      $stmt = $db->prepare( $sql );
      $stmt->execute( [ ':name' => $name, ':cif' => $cif, ':zar' => $zar, ':usd' => $usd, ':spread' => $spread, ':id' => $id ] );
    } else { // Insert
      $sql = "INSERT INTO clients (name, cif_number, zar_account, usd_account, spread) VALUES (:name, :cif, :zar, :usd, :spread)";
      $stmt = $db->prepare( $sql );
      $stmt->execute( [ ':name' => $name, ':cif' => $cif, ':zar' => $zar, ':usd' => $usd, ':spread' => $spread ] );
    }
    echo json_encode( [ 'success' => true ] );
  } catch ( PDOException $e ) {
    if ( $e->getCode() == 23000 ) {
       throw new Exception( 'A client with this CIF number already exists.' );
    } else {
       throw $e;
    }
  }
}

function handleDeleteClient( $db ) {
  $id = $_GET['id'] ?? null;
  if ( !$id ) {
    http_response_code( 400 );
    echo json_encode( [ 'success' => false, 'message' => 'No client ID provided.' ] );
    return;
  }
  $sql = "DELETE FROM clients WHERE id = :id";
  $stmt = $db->prepare( $sql );
  if ( $stmt->execute( [ ':id' => $id ] ) ) {
    echo json_encode( [ 'success' => true ] );
  } else {
    throw new Exception( "Failed to delete client." );
  }
}

function handleImportClients( $db ) {
  if ( !isset( $_FILES['csv'] ) || $_FILES['csv']['error'] !== UPLOAD_ERR_OK ) {
    http_response_code( 400 );
    echo json_encode( [ 'success' => false, 'message' => 'CSV file upload failed.' ] );
    return;
  }

  $csvFile = $_FILES['csv']['tmp_name'];
  $handle = fopen( $csvFile, 'r' );

  if ( $handle === false ) {
    throw new Exception( 'Could not open CSV file.' );
  }
  
  $header = fgetcsv( $handle );
  $clientIndex = array_search( 'Client', $header );
  $cifIndex = array_search( 'Client CIF', $header );
  $spreadIndex = array_search( 'Fixed Spread', $header );

  if ( $clientIndex === false || $cifIndex === false || $spreadIndex === false ) {
    http_response_code( 400 );
    echo json_encode( [ 'success' => false, 'message' => 'CSV must contain "Client", "Client CIF", and "Fixed Spread" columns.' ] );
    return;
  }
  
  $importedCount = 0;
  $db->beginTransaction();
  try {
    while ( ( $row = fgetcsv( $handle ) ) !== false ) {
      $name = $row[$clientIndex] ?? '';
      $cif = $row[$cifIndex] ?? '';
      $spread = $row[$spreadIndex] ?? '';
      // Expect spread as integer bips
      if ( !is_numeric( $spread ) || intval($spread) != $spread ) continue;
      $spread = intval($spread);
      if ( empty( $name ) || empty( $cif ) ) continue;
      // Upsert logic
      $updateSql = "UPDATE clients SET name = :name, spread = :spread WHERE cif_number = :cif";
      $insertSql = "INSERT INTO clients (name, cif_number, spread) VALUES (:name, :cif, :spread)";
      $updateStmt = $db->prepare( $updateSql );
      $insertStmt = $db->prepare( $insertSql );
      $updateStmt->execute( [ ':name' => $name, ':spread' => $spread, ':cif' => $cif ] );
      if ( $updateStmt->rowCount() === 0 ) {
         $insertStmt->execute( [ ':name' => $name, ':cif' => $cif, ':spread' => $spread ] );
      }
      $importedCount++;
    }
    $db->commit();
    echo json_encode( [ 'success' => true, 'imported' => $importedCount ] );
  } catch ( Exception $e ) {
    $db->rollBack();
    throw $e;
  }
}

/**
 * New Handler: Imports a trade batch from a CSV file.
 */
function handleImportTradesBatch( $db ) {
  if ( !isset( $_FILES['csv'] ) || $_FILES['csv']['error'] !== UPLOAD_ERR_OK ) {
    http_response_code( 400 );
    echo json_encode( [ 'success' => false, 'message' => 'Trade CSV file upload failed.' ] );
    return;
  }

  $csvFile = $_FILES['csv']['tmp_name'];
  $handle = fopen( $csvFile, 'r' );

  if ( $handle === false ) {
    throw new Exception( 'Could not open trade CSV file.' );
  }
  
  $header = fgetcsv( $handle );
  $cifIndex = array_search( 'Client CIF', $header );
  $amountIndex = array_search( 'Amount ZAR', $header );

  if ( $cifIndex === false || $amountIndex === false ) {
    http_response_code( 400 );
    echo json_encode( [ 'success' => false, 'message' => 'CSV must contain "Client CIF" and "Amount ZAR" columns.' ] );
    return;
  }
  
  // Read all trades into memory to get a total count
  $tradesToImport = [];
  while ( ( $row = fgetcsv( $handle ) ) !== false ) {
      $tradesToImport[] = $row;
  }
  fclose($handle);

  if (empty($tradesToImport)) {
    throw new Exception('No trade data found in CSV file.');
  }

  $totalTrades = count($tradesToImport);
  $batch_uid = 'batch_' . time();
  $now = date( 'Y-m-d H:i:s' );

  $db->beginTransaction();
  try {
    // Create batch record
    $batchStmt = $db->prepare( "INSERT INTO batches (batch_uid, status, total_trades, created_at) VALUES (?, ?, ?, ?)" );
    $batchStmt->execute( [ $batch_uid, 'Staged', $totalTrades, $now ] );
    $batch_id = $db->lastInsertId();

    // Prepare statements for finding client and inserting trade
    $clientStmt = $db->prepare("SELECT id FROM clients WHERE cif_number = ?");
    $tradeStmt = $db->prepare("INSERT INTO trades (batch_id, client_id, amount_zar, status, created_at) VALUES (?, ?, ?, ?, ?)");

    foreach ( $tradesToImport as $row ) {
      $cif = $row[$cifIndex] ?? '';
      $amount = $row[$amountIndex] ?? 0;

      if ( empty( $cif ) || !is_numeric( $amount ) || $amount <= 0 ) continue;

      // Find client_id from CIF
      $clientStmt->execute([$cif]);
      $clientId = $clientStmt->fetchColumn();

      if ($clientId) {
        $tradeStmt->execute( [ $batch_id, $clientId, $amount, 'Pending Validation', $now ] );
      } else {
        debug_log("Skipping trade for unknown CIF: {$cif}", 'IMPORT_WARN');
      }
    }

    $db->commit();
    echo json_encode( [ 'success' => true, 'message' => "Batch {$batch_uid} staged successfully with {$totalTrades} trades.", 'batch_uid' => $batch_uid ] );
  } catch ( Exception $e ) {
    $db->rollBack();
    throw $e;
  }
}

function handleFindZarAccount( $db ) {
  $cif = $_GET['cif'] ?? null;
  $clientId = $_GET['id'] ?? null;

  if ( !$cif || !$clientId ) {
    http_response_code( 400 );
    echo json_encode( [ 'success' => false, 'message' => 'Client ID and CIF number are required.' ] );
    return;
  }

  $stmt = $db->prepare( "SELECT account_no FROM bank_accounts WHERE cus_cif_no = :cif AND account_currency = 'ZAR' AND account_status = 'OPEN' ORDER BY account_no ASC LIMIT 1" );
  $stmt->execute( [ ':cif' => $cif ] );
  $account = $stmt->fetch();

  if ( $account ) {
    $foundAccount = $account['account_no'];
    $updateStmt = $db->prepare( "UPDATE clients SET zar_account = :zar_account WHERE id = :id" );
    $updateStmt->execute( [ ':zar_account' => $foundAccount, ':id' => $clientId ] );
    echo json_encode( [ 'success' => true, 'accountNumber' => $foundAccount ] );
  } else {
    http_response_code( 404 );
    echo json_encode( [ 'success' => false, 'message' => 'No active ZAR trading account found in local DB. Please sync first.' ] );
  }
}

function handleSyncBankAccounts( $db ) {
  $httpClient = new HttpClientService();
  $apiService = new CapitecApiService( $db, $httpClient );
  $syncedCount = $apiService->syncAllAccounts();
  echo json_encode( [ 'success' => true, 'synced_count' => $syncedCount ] );
}

function handleGetBankAccounts( $db ) {
  $stmt = $db->query( "SELECT * FROM bank_accounts ORDER BY cus_name, account_no ASC" );
  $accounts = $stmt->fetchAll();
  echo json_encode( $accounts );
}

function handleGetMigrations( $db ) {
  $migrationService = new MigrationService( $db );
  $available = $migrationService->getAvailableMigrations();
  $ran = $migrationService->getRanMigrations();
  echo json_encode( [ 'available' => $available, 'ran' => $ran ] );
}

function handleRunMigration( $db ) {
  $migrationFile = $_POST['migration'] ?? null;
  if ( !$migrationFile ) {
    http_response_code( 400 );
    echo json_encode( [ 'success' => false, 'message' => 'Migration filename not provided.' ] );
    return;
  }
  
  $migrationService = new MigrationService( $db );
  $migrationService->runMigration( $migrationFile );
  echo json_encode( [ 'success' => true, 'message' => "Migration '{$migrationFile}' ran successfully." ] );
}

function handleStageBatch( $db ) {
  $request_body = file_get_contents( 'php://input' );
  $data = json_decode( $request_body, true );

  if ( !isset( $data['trades'] ) || !is_array( $data['trades'] ) || empty( $data['trades'] ) ) {
    http_response_code( 400 );
    echo json_encode( [ 'success' => false, 'message' => 'No trades provided in the batch.' ] );
    return;
  }
  
  $db->beginTransaction();
  try {
    $batch_uid = 'batch_' . time();
    $now = date( 'Y-m-d H:i:s' );
    $totalTrades = count($data['trades']);

    // Create batch record
    $stmt = $db->prepare( "INSERT INTO batches (batch_uid, status, total_trades, created_at) VALUES (?, ?, ?, ?)" );
    $stmt->execute( [ $batch_uid, 'Staged', $totalTrades, $now ] );
    $batch_id = $db->lastInsertId();

    // Create trade records
    $trade_stmt = $db->prepare(
      "INSERT INTO trades (batch_id, client_id, amount_zar, status, created_at) VALUES (?, ?, ?, ?, ?)"
    );
    
    $trades_with_names = [];
    $client_info_stmt = $db->prepare( "SELECT name FROM clients WHERE id = ?" );

    foreach ( $data['trades'] as $trade ) {
      $trade_stmt->execute( [ $batch_id, $trade['clientId'], $trade['amount'], 'Pending Validation', $now ] );
      
      // Get client name for the response
      $client_info_stmt->execute( [ $trade['clientId'] ] );
      $client_name = $client_info_stmt->fetchColumn();
      
      $trades_with_names[] = [
        'client_name' => $client_name,
        'amount_zar' => $trade['amount'],
        'status' => 'Pending Validation'
      ];
    }

    $db->commit();
    
    $response_batch = [
      'batch_uid' => $batch_uid,
      'trades' => $trades_with_names
    ];

    echo json_encode( [ 'success' => true, 'batch' => $response_batch ] );

  } catch ( Exception $e ) {
    $db->rollBack();
    throw $e;
  }
}

function handleGetMarketAnalysis( $db ) {
  $httpClient = new HttpClientService();
  $geminiService = new GeminiApiService( $httpClient );
  $analysis = $geminiService->getUsdZarAnalysis();
  echo json_encode( [ 'success' => true, 'analysis' => $analysis ] );
}

function handleVerifySchema( $db ) {
  $migrationService = new MigrationService( $db );
  $errors = $migrationService->verifySchema();
  $isValid = empty( $errors['missing_tables'] ) && empty( $errors['missing_columns'] );
  if ( !$isValid ) http_response_code( 400 );
  echo json_encode( [ 'success' => true, 'is_valid' => $isValid, 'errors' => $errors ] );
}
