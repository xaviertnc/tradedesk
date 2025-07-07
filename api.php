<?php
// api.php - Main backend handler

// --- Error Reporting (for development) ---
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// --- Server-Side Logging ---
function debug_log($var, $pretext = '', $minDebugLevel = 1, $type = 'DEBUG', $format = 'text') {
    // For this app, we'll keep it simple and always log.
    $log_file = __DIR__ . '/debug.log';
    $timestamp = date('Y-m-d H:i:s');
    $log_entry = "[$timestamp] [$type] $pretext: ";

    if (is_string($var) || is_numeric($var)) {
        $log_entry .= $var;
    } else {
        $log_entry .= print_r($var, true);
    }

    file_put_contents($log_file, $log_entry . PHP_EOL, FILE_APPEND);
}

// --- Response Header ---
header('Content-Type: application/json');

// --- Database Setup ---
function getDbConnection() {
    $db_file = 'fx_trader.db';
    $is_new_db = !file_exists($db_file);
    
    try {
        $pdo = new PDO('sqlite:' . $db_file);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Database connection failed: ' . $e->getMessage()]);
        exit;
    }

    if ($is_new_db) {
        debug_log('Database file not found, creating new database and tables.');
        try {
            // Config Table
            $pdo->exec("CREATE TABLE IF NOT EXISTS config (
                id INTEGER PRIMARY KEY,
                api_base_url TEXT,
                auth_url TEXT,
                client_id TEXT,
                client_secret TEXT,
                username TEXT,
                password TEXT,
                api_external_token TEXT,
                otc_rate REAL,
                access_token TEXT,
                token_expiry INTEGER
            )");
            // **FIX**: Add default value for token on initial creation
            $pdo->exec("INSERT INTO config (id, api_external_token) SELECT 1, 'YOUR_INTERMEDIATE_TOKEN' WHERE NOT EXISTS (SELECT 1 FROM config WHERE id = 1)");

            // Clients Table
            $pdo->exec("CREATE TABLE IF NOT EXISTS clients (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                name TEXT NOT NULL,
                cif_number TEXT NOT NULL UNIQUE,
                zar_account TEXT,
                usd_account TEXT,
                spread REAL NOT NULL
            )");

            // Bank Accounts Table
            $pdo->exec("CREATE TABLE IF NOT EXISTS bank_accounts (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                cus_cif_no TEXT NOT NULL,
                cus_name TEXT,
                account_no TEXT NOT NULL,
                account_type TEXT,
                account_status TEXT,
                account_currency TEXT,
                curr_account_balance REAL,
                UNIQUE(account_no)
            )");

            // Trades Table
            $pdo->exec("CREATE TABLE IF NOT EXISTS trades (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                client_id INTEGER,
                status TEXT,
                status_message TEXT,
                bank_quote_id TEXT,
                bank_rate REAL,
                client_rate REAL,
                amount_zar REAL,
                bank_trxn_id TEXT,
                created_at TEXT,
                FOREIGN KEY (client_id) REFERENCES clients(id)
            )");
        } catch (PDOException $e) {
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Database table creation failed: ' . $e->getMessage()]);
            exit;
        }
    } else {
        // **FIX**: Simple migration to add the column if it doesn't exist
        try {
            $pdo->query("SELECT api_external_token FROM config LIMIT 1");
        } catch (PDOException $e) {
            if (strpos($e->getMessage(), 'no such column') !== false) {
                debug_log('Column "api_external_token" not found in config table, adding it now.');
                $pdo->exec("ALTER TABLE config ADD COLUMN api_external_token TEXT DEFAULT 'YOUR_INTERMEDIATE_TOKEN'");
            } else {
                throw $e;
            }
        }
    }
    return $pdo;
}

// --- Main Router ---
$action = $_GET['action'] ?? '';
$db = getDbConnection();

try {
    debug_log("Processing action: '{$action}'");
    switch ($action) {
        case 'get_config': handleGetConfig($db); break;
        case 'save_config': handleSaveConfig($db); break;
        case 'get_clients': handleGetClients($db); break;
        case 'save_client': handleSaveClient($db); break;
        case 'delete_client': handleDeleteClient($db); break;
        case 'import_clients': handleImportClients($db); break;
        case 'find_zar_account': handleFindZarAccount($db); break;
        case 'sync_bank_accounts': handleSyncBankAccounts($db); break;
        case 'get_bank_accounts': handleGetBankAccounts($db); break;
        default:
            http_response_code(404);
            echo json_encode(['success' => false, 'message' => 'Action not found']);
    }
} catch (Exception $e) {
    http_response_code(500);
    debug_log("Caught exception in api.php: " . $e->getMessage(), 'FATAL', 1, 'ERROR');
    echo json_encode(['success' => false, 'message' => 'An internal server error occurred: ' . $e->getMessage()]);
}


// --- API Communication Helper ---
function makeApiRequest($url, $method = 'GET', $payload = null, $headers = []) {
    $ch = curl_init();
    if ($ch === false) {
        throw new Exception("Failed to initialize cURL.");
    }
    $defaultHeaders = ['Content-Type: application/json'];

    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 60);
    curl_setopt($ch, CURLOPT_HTTPHEADER, array_merge($defaultHeaders, $headers));

    if ($method === 'POST') {
        curl_setopt($ch, CURLOPT_POST, true);
        if ($payload) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
        }
    }
    
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);

    if ($error) {
        throw new Exception("cURL Error: " . $error);
    }
    
    $responseData = json_decode($response, true);

    if ($http_code >= 400) {
        $errorMessage = $responseData['result']['resultMsg'] ?? $responseData['error_description'] ?? 'API request failed.';
        throw new Exception("API Error (HTTP $http_code): " . $errorMessage);
    }

    return $responseData;
}

// --- Handler Functions ---

function handleGetConfig($db) {
    $stmt = $db->query("SELECT * FROM config WHERE id = 1");
    $config = $stmt->fetch();
    echo json_encode($config ?: (object)[]);
}

function handleSaveConfig($db) {
    $fields = ['api_base_url', 'auth_url', 'client_id', 'client_secret', 'username', 'password', 'api_external_token', 'otc_rate'];
    $updates = [];
    $params = [];
    foreach ($fields as $field) {
        if (isset($_POST[$field])) {
            $updates[] = "$field = :$field";
            $params[":$field"] = $_POST[$field];
        }
    }
    
    if (empty($updates)) {
        echo json_encode(['success' => false, 'message' => 'No data provided']);
        return;
    }

    $params[':id'] = 1;
    $sql = "UPDATE config SET " . implode(', ', $updates) . " WHERE id = :id";
    $stmt = $db->prepare($sql);

    if ($stmt->execute($params)) {
        echo json_encode(['success' => true]);
    } else {
        throw new Exception("Failed to save settings.");
    }
}

function handleGetClients($db) {
    $stmt = $db->query("SELECT * FROM clients ORDER BY name ASC");
    $clients = $stmt->fetchAll();
    echo json_encode($clients);
}

function handleSaveClient($db) {
    $id = $_POST['id'] ?? null;
    $name = $_POST['client-name'] ?? '';
    $cif = $_POST['client-cif'] ?? '';
    $zar = $_POST['client-zar'] ?? '';
    $usd = $_POST['client-usd'] ?? '';
    $spread = $_POST['client-spread'] ?? 0;

    if (empty($name) || empty($cif) || !is_numeric($spread)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Invalid data provided.']);
        return;
    }

    try {
        if ($id) { // Update
            $sql = "UPDATE clients SET name = :name, cif_number = :cif, zar_account = :zar, usd_account = :usd, spread = :spread WHERE id = :id";
            $stmt = $db->prepare($sql);
            $stmt->execute([':name' => $name, ':cif' => $cif, ':zar' => $zar, ':usd' => $usd, ':spread' => $spread, ':id' => $id]);
        } else { // Insert
            $sql = "INSERT INTO clients (name, cif_number, zar_account, usd_account, spread) VALUES (:name, :cif, :zar, :usd, :spread)";
            $stmt = $db->prepare($sql);
            $stmt->execute([':name' => $name, ':cif' => $cif, ':zar' => $zar, ':usd' => $usd, ':spread' => $spread]);
        }
        echo json_encode(['success' => true]);
    } catch (PDOException $e) {
        if ($e->getCode() == 23000) {
             throw new Exception('A client with this CIF number already exists.');
        } else {
             throw $e;
        }
    }
}

function handleDeleteClient($db) {
    $id = $_GET['id'] ?? null;
    if (!$id) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'No client ID provided.']);
        return;
    }
    $sql = "DELETE FROM clients WHERE id = :id";
    $stmt = $db->prepare($sql);
    if ($stmt->execute([':id' => $id])) {
        echo json_encode(['success' => true]);
    } else {
        throw new Exception("Failed to delete client.");
    }
}

function handleImportClients($db) {
    if (!isset($_FILES['csv']) || $_FILES['csv']['error'] !== UPLOAD_ERR_OK) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'CSV file upload failed.']);
        return;
    }

    $csvFile = $_FILES['csv']['tmp_name'];
    $handle = fopen($csvFile, 'r');

    if ($handle === false) {
        throw new Exception('Could not open CSV file.');
    }
    
    $header = fgetcsv($handle);
    $clientIndex = array_search('Client', $header);
    $cifIndex = array_search('Client CIF', $header);
    $spreadIndex = array_search('Fixed Spread', $header);

    if ($clientIndex === false || $cifIndex === false || $spreadIndex === false) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'CSV must contain "Client", "Client CIF", and "Fixed Spread" columns.']);
        return;
    }
    
    $importedCount = 0;
    $db->beginTransaction();
    try {
        $insertSql = "INSERT INTO clients (name, cif_number, spread) VALUES (:name, :cif, :spread)";
        $updateSql = "UPDATE clients SET name = :name, spread = :spread WHERE cif_number = :cif";
        
        $insertStmt = $db->prepare($insertSql);
        $updateStmt = $db->prepare($updateSql);
        $selectStmt = $db->prepare("SELECT id FROM clients WHERE cif_number = ?");

        while (($row = fgetcsv($handle)) !== false) {
            $name = $row[$clientIndex];
            $cif = $row[$cifIndex];
            $spread = (float)$row[$spreadIndex] / 100;

            if (empty($name) || empty($cif) || !is_numeric($spread)) continue;
            
            $selectStmt->execute([$cif]);
            $exists = $selectStmt->fetch();

            if ($exists) {
                $updateStmt->execute([':name' => $name, ':spread' => $spread, ':cif' => $cif]);
            } else {
                $insertStmt->execute([':name' => $name, ':cif' => $cif, ':spread' => $spread]);
            }
            $importedCount++;
        }
        $db->commit();
        echo json_encode(['success' => true, 'imported_count' => $importedCount]);

    } catch (Exception $e) {
        $db->rollBack();
        throw $e;
    } finally {
        fclose($handle);
    }
}

function handleFindZarAccount($db) {
    $cif = $_GET['cif'] ?? null;
    $clientId = $_GET['id'] ?? null;

    if (!$cif || !$clientId) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Client ID and CIF number are required.']);
        return;
    }

    $stmt = $db->prepare("SELECT account_no FROM bank_accounts WHERE cus_cif_no = :cif AND account_currency = 'ZAR' AND account_status = 'OPEN' ORDER BY account_no ASC LIMIT 1");
    $stmt->execute([':cif' => $cif]);
    $account = $stmt->fetch();

    if ($account) {
        $foundAccount = $account['account_no'];
        $updateStmt = $db->prepare("UPDATE clients SET zar_account = :zar_account WHERE id = :id");
        $updateStmt->execute([':zar_account' => $foundAccount, ':id' => $clientId]);
        echo json_encode(['success' => true, 'accountNumber' => $foundAccount]);
    } else {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'No active ZAR trading account found in local DB. Please sync first.']);
    }
}

function handleSyncBankAccounts($db) {
    debug_log('Starting bank account sync process...');
    $configStmt = $db->query("SELECT * FROM config WHERE id = 1");
    $config = $configStmt->fetch();

    $required_keys = ['api_base_url', 'auth_url', 'client_id', 'client_secret', 'username', 'password', 'api_external_token'];
    foreach($required_keys as $key) {
        if (empty($config[$key])) {
             throw new Exception("API setting '$key' is not configured in Settings.");
        }
    }
    debug_log('Configuration loaded successfully.');
    
    // Step 1: Get OAuth Token
    debug_log('Requesting OAuth token...');
    $tokenPayload = http_build_query([
        'client_id' => $config['client_id'],
        'client_secret' => $config['client_secret'],
        'grant_type' => 'password',
        'username' => $config['username'],
        'password' => $config['password'],
        'scope' => 'offline_access api://' . $config['client_id'] . '/.default',
    ]);

    $ch = curl_init($config['auth_url']);
    if ($ch === false) {
        throw new Exception("Failed to initialize cURL for Auth URL. Is the URL valid in Settings?");
    }

    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $tokenPayload,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/x-www-form-urlencoded',
            'Authorization: Bearer ' . $config['api_external_token']
        ]
    ]);
    $tokenResponse = curl_exec($ch);
    $tokenHttpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $tokenError = curl_error($ch);
    curl_close($ch);

    if ($tokenError) throw new Exception("Auth cURL Error: " . $tokenError);
    
    $tokenData = json_decode($tokenResponse, true);
    
    if ($tokenHttpCode >= 400 || !is_array($tokenData) || !isset($tokenData['access_token'])) {
        $errorMsg = 'Failed to obtain access token.';
        if (is_array($tokenData) && isset($tokenData['error_description'])) {
            $errorMsg = $tokenData['error_description'];
        } elseif (!is_array($tokenData) && !empty($tokenResponse)) {
            $errorMsg = "Invalid response from auth server. Check credentials and external token.";
        }
        debug_log("Auth API Error (HTTP $tokenHttpCode): $errorMsg", 'AUTH_FAILURE', 1, 'ERROR');
        throw new Exception("Auth API Error (HTTP $tokenHttpCode): " . $errorMsg);
    }
    $accessToken = $tokenData['access_token'];
    debug_log('Successfully obtained access token.');

    // Step 2: Call Bulk Balance Enquiry API
    debug_log('Starting bulk balance enquiry...');
    $allAccounts = [];
    $page = 0;
    $totalPages = 1;
    $balanceUrl = rtrim($config['api_base_url'], '/') . '/account/api/v1/bulk-balance';

    do {
        debug_log("Fetching page {$page} of balances...");
        $balancePayload = [
            'header' => ['userId' => $config['username']],
            'page' => $page,
            'size' => 100
        ];
        $apiHeaders = ['Authorization: Bearer ' . $accessToken];
        $balanceData = makeApiRequest($balanceUrl, 'POST', $balancePayload, $apiHeaders);

        if (isset($balanceData['payload']['content'])) {
            $count = count($balanceData['payload']['content']);
            debug_log("Page {$page} contained {$count} accounts.");
            $allAccounts = array_merge($allAccounts, $balanceData['payload']['content']);
            $totalPages = $balanceData['payload']['page']['totalPages'] ?? $totalPages;
        } else {
            if ($page === 0) {
                throw new Exception("API response missing 'content' payload.");
            }
        }
        $page++;
    } while ($page < $totalPages);
    debug_log('Finished fetching all pages from bulk balance API. Total accounts fetched: ' . count($allAccounts));

    // Step 3: Filter and save to DB
    $syncedCount = 0;
    if (!empty($allAccounts)) {
        $db->beginTransaction();
        $sql = "INSERT OR REPLACE INTO bank_accounts (cus_cif_no, cus_name, account_no, account_type, account_status, account_currency, curr_account_balance) 
                VALUES (:cus_cif_no, :cus_name, :account_no, :account_type, :account_status, :account_currency, :curr_account_balance)";
        $stmt = $db->prepare($sql);

        foreach ($allAccounts as $account) {
            if (isset($account['accountType']) && strtoupper($account['accountType']) === 'FX TRADE ACCOUNT') {
                $stmt->execute([
                    ':cus_cif_no' => $account['cusCifNo'],
                    ':cus_name' => $account['cusName'],
                    ':account_no' => $account['accountNo'],
                    ':account_type' => $account['accountType'],
                    ':account_status' => $account['accountStatus'],
                    ':account_currency' => $account['accountCurrency'],
                    ':curr_account_balance' => $account['currAccountBalance']
                ]);
                $syncedCount++;
            }
        }
        $db->commit();
    }
    debug_log("Sync complete. Saved {$syncedCount} 'FX TRADE ACCOUNT' type accounts to the database.");

    echo json_encode(['success' => true, 'synced_count' => $syncedCount]);
}

function handleGetBankAccounts($db) {
    $stmt = $db->query("SELECT * FROM bank_accounts ORDER BY cus_name, account_no ASC");
    $accounts = $stmt->fetchAll();
    echo json_encode($accounts);
}
