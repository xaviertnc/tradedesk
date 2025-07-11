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
 * @version 1.2 - FEAT - 10 Jul 2025 - Add batch progress and results endpoints
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
require_once __DIR__ . '/BatchService.php';
require_once __DIR__ . '/Model.php';
require_once __DIR__ . '/Batch.php';
require_once __DIR__ . '/Trade.php';

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

// --- Response Header (will be overridden for WebSocket) ---

// --- Database Setup ---
function getDbConnection() {
  $db_file = 'data' . DIRECTORY_SEPARATOR . 'tradedesk.db';

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

// Set JSON content type for all actions except WebSocket
if ( $action !== 'websocket' ) {
  header( 'Content-Type: application/json' );
}

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
    case 'get_batches': handleGetBatches( $db ); break;
    case 'get_batch': handleGetBatch( $db ); break;
    case 'get_batch_progress': handleGetBatchProgress( $db ); break;
    case 'get_batch_results': handleGetBatchResults( $db ); break;
    case 'get_batch_errors': handleGetBatchErrors( $db ); break;
    case 'start_batch': handleStartBatch( $db ); break;
    case 'upload_batch_csv': handleUploadBatchCsv( $db ); break;
    case 'cancel_batch': handleCancelBatch( $db ); break;
    case 'delete_batch': handleDeleteBatch( $db ); break;
    case 'get_active_batches': handleGetActiveBatches( $db ); break;
    case 'get_recent_batches': handleGetRecentBatches( $db ); break;
    case 'get_locked_batches': handleGetLockedBatches( $db ); break;
    case 'set_batch_priority': handleSetBatchPriority( $db ); break;
    case 'cleanup_expired_locks': handleCleanupExpiredLocks( $db ); break;
    case 'get_next_batch_from_queue': handleGetNextBatchFromQueue( $db ); break;
    case 'get_market_analysis': handleGetMarketAnalysis( $db ); break;
    case 'verify_schema': handleVerifySchema( $db ); break;
    case 'websocket': handleWebSocket( $db ); break; // New WebSocket endpoint
    case 'search_batches': handleSearchBatches( $db ); break; // New Search endpoint
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
  header('Content-Type: application/json');
  if ( !$migrationFile ) {
    http_response_code( 400 );
    echo json_encode( [ 'success' => false, 'message' => 'Migration filename not provided.' ] );
    return;
  }

  try {
    $migrationService = new MigrationService( $db );
    ob_start(); // Buffer any accidental output
    $migrationService->runMigration( $migrationFile );
    $output = ob_get_clean();
    echo json_encode( [ 'success' => true, 'message' => "Migration '{$migrationFile}' ran successfully.", 'output' => $output ] );
  } catch ( Exception $e ) {
    http_response_code( 500 );
    $output = ob_get_clean();
    echo json_encode( [ 'success' => false, 'message' => $e->getMessage(), 'output' => $output ] );
  }
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


function handleGetBatches( $db ) {
  try {
    $stmt = $db->query( "
      SELECT 
        b.id,
        b.batch_uid,
        b.status,
        b.total_trades,
        b.processed_trades,
        b.failed_trades,
        b.created_at,
        b.updated_at
      FROM batches b 
      ORDER BY b.created_at DESC
    " );
    $batches = $stmt->fetchAll();
    echo json_encode( [ 'success' => true, 'batches' => $batches ] );
  } catch ( Exception $e ) {
    http_response_code( 500 );
    echo json_encode( [ 'success' => false, 'message' => 'Failed to fetch batches: ' . $e->getMessage() ] );
  }
}


function handleGetBatch( $db ) {
  $batchId = $_GET['id'] ?? null;
  if ( !$batchId ) {
    http_response_code( 400 );
    echo json_encode( [ 'success' => false, 'message' => 'Batch ID is required.' ] );
    return;
  }

  try {
    // Get batch details
    $batchStmt = $db->prepare( "
      SELECT 
        b.id,
        b.batch_uid,
        b.status,
        b.total_trades,
        b.processed_trades,
        b.failed_trades,
        b.created_at,
        b.updated_at
      FROM batches b 
      WHERE b.id = ?
    " );
    $batchStmt->execute( [ $batchId ] );
    $batch = $batchStmt->fetch();

    if ( !$batch ) {
      http_response_code( 404 );
      echo json_encode( [ 'success' => false, 'message' => 'Batch not found.' ] );
      return;
    }

    // Get trades for this batch
    $tradesStmt = $db->prepare( "
      SELECT 
        t.id,
        t.client_id,
        t.status,
        t.status_message,
        t.quote_id,
        t.quote_rate,
        t.amount_zar,
        t.bank_trxn_id,
        t.deal_ref,
        t.last_error,
        t.created_at,
        c.name as client_name,
        c.cif_number
      FROM trades t
      LEFT JOIN clients c ON t.client_id = c.id
      WHERE t.batch_id = ?
      ORDER BY t.created_at ASC
    " );
    $tradesStmt->execute( [ $batchId ] );
    $trades = $tradesStmt->fetchAll();

    echo json_encode( [ 
      'success' => true, 
      'batch' => $batch,
      'trades' => $trades 
    ] );
  } catch ( Exception $e ) {
    http_response_code( 500 );
    echo json_encode( [ 'success' => false, 'message' => 'Failed to fetch batch: ' . $e->getMessage() ] );
  }
}


function handleGetBatchProgress( $db ) {
  $batchId = $_GET['id'] ?? null;
  if ( !$batchId ) {
    http_response_code( 400 );
    echo json_encode( [ 'success' => false, 'message' => 'Batch ID is required.' ] );
    return;
  }

  try {
    $batchService = new BatchService( $db );
    $progress = $batchService->getBatchProgress( (int)$batchId );
    echo json_encode( [ 'success' => true, 'progress' => $progress ] );
  } catch ( Exception $e ) {
    http_response_code( 500 );
    echo json_encode( [ 'success' => false, 'message' => 'Failed to get batch progress: ' . $e->getMessage() ] );
  }
}


function handleGetBatchResults( $db ) {
  $batchId = $_GET['id'] ?? null;
  if ( !$batchId ) {
    http_response_code( 400 );
    echo json_encode( [ 'success' => false, 'message' => 'Batch ID is required.' ] );
    return;
  }

  try {
    $batchService = new BatchService( $db );
    $results = $batchService->getBatchResults( (int)$batchId );
    echo json_encode( [ 'success' => true, 'results' => $results ] );
  } catch ( Exception $e ) {
    http_response_code( 500 );
    echo json_encode( [ 'success' => false, 'message' => 'Failed to get batch results: ' . $e->getMessage() ] );
  }
}


function handleGetBatchErrors( $db ) {
  $batchId = $_GET['id'] ?? null;
  if ( !$batchId ) {
    http_response_code( 400 );
    echo json_encode( [ 'success' => false, 'message' => 'Batch ID is required.' ] );
    return;
  }

  try {
    require_once __DIR__ . '/BatchService.php';
    $batchService = new BatchService( $db );
    $errors = $batchService->getBatchErrors( (int)$batchId );

    if ( $errors ) {
      echo json_encode( [ 'success' => true, 'data' => $errors ] );
    } else {
      http_response_code( 404 );
      echo json_encode( [ 'success' => false, 'message' => 'Batch not found.' ] );
    }
  } catch ( Exception $e ) {
    http_response_code( 500 );
    echo json_encode( [ 'success' => false, 'message' => 'Failed to get batch errors: ' . $e->getMessage() ] );
  }
}


function handleStartBatch( $db ) {
  $batchId = $_POST['batch_id'] ?? null;
  if ( !$batchId ) {
    http_response_code( 400 );
    echo json_encode( [ 'success' => false, 'message' => 'Batch ID is required.' ] );
    return;
  }

  try {
    require_once __DIR__ . '/BatchService.php';
    $batchService = new BatchService( $db );
    $success = $batchService->runBatchAsync( (int)$batchId );
    
    if ( $success ) {
      echo json_encode( [ 
        'success' => true, 
        'message' => 'Batch processing started successfully.',
        'batch_id' => $batchId
      ] );
    } else {
      http_response_code( 400 );
      echo json_encode( [ 'success' => false, 'message' => 'Failed to start batch processing.' ] );
    }
  } catch ( Exception $e ) {
    http_response_code( 500 );
    echo json_encode( [ 'success' => false, 'message' => 'Error starting batch: ' . $e->getMessage() ] );
  }
}


function handleUploadBatchCsv( $db ) {
  if ( !isset( $_FILES['csv'] ) || $_FILES['csv']['error'] !== UPLOAD_ERR_OK ) {
    http_response_code( 400 );
    echo json_encode( [ 'success' => false, 'message' => 'CSV file upload failed.' ] );
    return;
  }

  try {
    $batchService = new BatchService( $db );
    $csvFilePath = $_FILES['csv']['tmp_name'];
    $batchId = $batchService->createBatchFromCsv( $csvFilePath );
    
    echo json_encode( [ 
      'success' => true, 
      'message' => 'Batch created successfully from CSV.',
      'batch_id' => $batchId 
    ] );
  } catch ( Exception $e ) {
    http_response_code( 500 );
    echo json_encode( [ 'success' => false, 'message' => 'Failed to create batch: ' . $e->getMessage() ] );
  }
}


function handleCancelBatch( $db ) {
  $batchId = $_POST['batch_id'] ?? null;
  if ( !$batchId ) {
    http_response_code( 400 );
    echo json_encode( [ 'success' => false, 'message' => 'Batch ID is required.' ] );
    return;
  }

  try {
    $batchService = new BatchService( $db );
    $success = $batchService->cancelBatch( (int)$batchId );
    
    if ( $success ) {
      echo json_encode( [ 
        'success' => true, 
        'message' => 'Batch cancelled successfully.' 
      ] );
    } else {
      http_response_code( 404 );
      echo json_encode( [ 'success' => false, 'message' => 'Batch not found or could not be cancelled.' ] );
    }
  } catch ( Exception $e ) {
    http_response_code( 500 );
    echo json_encode( [ 'success' => false, 'message' => 'Failed to cancel batch: ' . $e->getMessage() ] );
  }
} // handleCancelBatch


function handleDeleteBatch( $db ) {
  $batchId = $_POST['batch_id'] ?? null;
  if ( !$batchId ) {
    http_response_code( 400 );
    echo json_encode( [ 'success' => false, 'message' => 'Batch ID is required.' ] );
    return;
  }

  try {
    $batchService = new BatchService( $db );
    $success = $batchService->deleteBatch( (int)$batchId );
    
    if ( $success ) {
      echo json_encode( [ 
        'success' => true, 
        'message' => 'Batch deleted successfully.' 
      ] );
    } else {
      http_response_code( 404 );
      echo json_encode( [ 'success' => false, 'message' => 'Batch not found or could not be deleted.' ] );
    }
  } catch ( Exception $e ) {
    http_response_code( 500 );
    echo json_encode( [ 'success' => false, 'message' => 'Failed to delete batch: ' . $e->getMessage() ] );
  }
} // handleDeleteBatch


function handleGetActiveBatches( $db ) {
  try {
    $batchService = new BatchService( $db );
    $activeBatches = $batchService->getActiveBatches();
    echo json_encode( [ 'success' => true, 'batches' => $activeBatches ] );
  } catch ( Exception $e ) {
    http_response_code( 500 );
    echo json_encode( [ 'success' => false, 'message' => 'Failed to get active batches: ' . $e->getMessage() ] );
  }
} // handleGetActiveBatches


function handleGetRecentBatches( $db ) {
  $limit = $_GET['limit'] ?? 10;
  try {
    $batchService = new BatchService( $db );
    $recentBatches = $batchService->getRecentCompletedBatches( (int)$limit );
    echo json_encode( [ 'success' => true, 'batches' => $recentBatches ] );
  } catch ( Exception $e ) {
    http_response_code( 500 );
    echo json_encode( [ 'success' => false, 'message' => 'Failed to get recent batches: ' . $e->getMessage() ] );
  }
} // handleGetRecentBatches


function handleGetLockedBatches( $db ) {
  try {
    $batchService = new BatchService( $db );
    $lockedBatches = $batchService->getLockedBatches();
    echo json_encode( [ 'success' => true, 'batches' => $lockedBatches ] );
  } catch ( Exception $e ) {
    http_response_code( 500 );
    echo json_encode( [ 'success' => false, 'message' => 'Failed to get locked batches: ' . $e->getMessage() ] );
  }
} // handleGetLockedBatches


function handleSetBatchPriority( $db ) {
  $batchId = $_POST['batch_id'] ?? null;
  $priority = $_POST['priority'] ?? null;
  
  if ( !$batchId || !$priority ) {
    http_response_code( 400 );
    echo json_encode( [ 'success' => false, 'message' => 'Batch ID and priority are required.' ] );
    return;
  }

  try {
    $batchService = new BatchService( $db );
    $success = $batchService->setBatchPriority( (int)$batchId, (int)$priority );
    
    if ( $success ) {
      echo json_encode( [ 
        'success' => true, 
        'message' => 'Batch priority updated successfully.' 
      ] );
    } else {
      http_response_code( 400 );
      echo json_encode( [ 'success' => false, 'message' => 'Failed to update batch priority.' ] );
    }
  } catch ( Exception $e ) {
    http_response_code( 500 );
    echo json_encode( [ 'success' => false, 'message' => 'Error updating batch priority: ' . $e->getMessage() ] );
  }
} // handleSetBatchPriority


function handleCleanupExpiredLocks( $db ) {
  try {
    $batchService = new BatchService( $db );
    $cleanedCount = $batchService->cleanupExpiredLocks();
    echo json_encode( [ 
      'success' => true, 
      'message' => "Cleaned up {$cleanedCount} expired locks.",
      'cleaned_count' => $cleanedCount
    ] );
  } catch ( Exception $e ) {
    http_response_code( 500 );
    echo json_encode( [ 'success' => false, 'message' => 'Failed to cleanup expired locks: ' . $e->getMessage() ] );
  }
} // handleCleanupExpiredLocks


function handleGetNextBatchFromQueue( $db ) {
  try {
    $batchService = new BatchService( $db );
    $nextBatchId = $batchService->getNextBatchFromQueue();
    
    if ( $nextBatchId ) {
      echo json_encode( [ 
        'success' => true, 
        'next_batch_id' => $nextBatchId,
        'message' => 'Next batch found in queue.'
      ] );
    } else {
      echo json_encode( [ 
        'success' => true, 
        'next_batch_id' => null,
        'message' => 'No batches in queue.'
      ] );
    }
  } catch ( Exception $e ) {
    http_response_code( 500 );
    echo json_encode( [ 'success' => false, 'message' => 'Failed to get next batch from queue: ' . $e->getMessage() ] );
  }
} // handleGetNextBatchFromQueue

// WebSocket support for real-time updates
function handleWebSocket( $db ) {
  // Suppress all output and errors for SSE
  @ini_set( 'display_errors', 0 );
  @ini_set( 'display_startup_errors', 0 );
  @error_reporting( 0 );
  while ( ob_get_level() > 0 ) { ob_end_clean(); }

  header( 'Content-Type: text/event-stream' );
  header( 'Cache-Control: no-cache' );
  header( 'Connection: keep-alive' );
  header( 'Access-Control-Allow-Origin: *' );
  header( 'Access-Control-Allow-Headers: Cache-Control' );
  
  // Send initial connection message
  echo "data: " . json_encode( [ 'type' => 'connection', 'status' => 'connected', 'timestamp' => time() ] ) . "\n\n";
  ob_flush();
  flush();
  
  // Keep connection alive and send updates
  $lastUpdate = time();
  $maxDuration = 300; // 5 minutes max connection
  
  while ( ( time() - $lastUpdate ) < $maxDuration ) {
    // Check for new batch updates
    $batchService = new BatchService( $db );
    $activeBatches = $batchService->getActiveBatches();
    $recentUpdates = $batchService->getRecentUpdates( $lastUpdate );
    
    if ( ! empty( $recentUpdates ) ) {
      foreach ( $recentUpdates as $update ) {
        echo "data: " . json_encode( $update ) . "\n\n";
        ob_flush();
        flush();
      }
      $lastUpdate = time();
    }
    
    // Send heartbeat every 30 seconds
    if ( ( time() - $lastUpdate ) >= 30 ) {
      echo "data: " . json_encode( [ 'type' => 'heartbeat', 'timestamp' => time() ] ) . "\n\n";
      ob_flush();
      flush();
      $lastUpdate = time();
    }
    
    usleep( 1000000 ); // Sleep for 1 second
  }
  
  echo "data: " . json_encode( [ 'type' => 'disconnect', 'status' => 'timeout', 'timestamp' => time() ] ) . "\n\n";
  exit;
}

// Enhanced batch search and filtering
function handleSearchBatches( $db ) {
  $status = $_GET['status'] ?? null;
  $dateFrom = $_GET['date_from'] ?? null;
  $dateTo = $_GET['date_to'] ?? null;
  $page = (int) ( $_GET['page'] ?? 1 );
  $limit = (int) ( $_GET['limit'] ?? 20 );
  $sortBy = $_GET['sort_by'] ?? 'created_at';
  $sortOrder = $_GET['sort_order'] ?? 'DESC';
  
  $filters = [];
  if ( $status ) $filters['status'] = $status;
  if ( $dateFrom ) $filters['date_from'] = $dateFrom;
  if ( $dateTo ) $filters['date_to'] = $dateTo;
  
  $batchService = new BatchService( $db );
  $result = $batchService->searchBatches( $filters, $page, $limit, $sortBy, $sortOrder );
  
  header( 'Content-Type: application/json' );
  echo json_encode( $result );
  exit;
}
