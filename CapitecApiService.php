<?php
// CapitecApiService.php

/**
 * Handles all direct API communication with the Capitec Forex endpoints.
 */
class CapitecApiService {
    private PDO $db;
    private array $config;

    public function __construct(PDO $db) {
        $this->db = $db;
        $this->loadConfig();
    }

    private function loadConfig(): void {
        $stmt = $this->db->query("SELECT * FROM config WHERE id = 1");
        $config = $stmt->fetch();
        if (!$config) {
            throw new Exception("API configuration not found in the database.");
        }
        $this->config = $config;
    }

    private function getAuthToken(): string {
        debug_log('Requesting OAuth token...');
        $tokenPayload = http_build_query([
            'client_id' => $this->config['client_id'],
            'client_secret' => $this->config['client_secret'],
            'grant_type' => 'password',
            'username' => $this->config['username'],
            'password' => $this->config['password'],
            'scope' => 'offline_access api://' . $this->config['client_id'] . '/.default',
        ]);

        $authHeaders = [
            'Content-Type: application/x-www-form-urlencoded',
            'Authorization: Bearer ' . $this->config['api_external_token']
        ];

        // Auth requests don't use the standard makeApiRequest because the payload isn't JSON
        $ch = curl_init($this->config['auth_url']);
        if ($ch === false) {
            throw new Exception("Failed to initialize cURL for Auth URL. Is the URL valid in Settings?");
        }

        debug_log([
            'URL' => $this->config['auth_url'],
            'Method' => 'POST',
            'Headers' => ['Authorization: Bearer ' . substr($this->config['api_external_token'], 0, 8) . '...'],
            'Payload' => $tokenPayload
        ], '--- Sending Auth Request ---');

        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $tokenPayload,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => $authHeaders,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false,
        ]);
        $tokenResponse = curl_exec($ch);
        $tokenHttpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $tokenError = curl_error($ch);
        curl_close($ch);

        debug_log([
            'URL' => $this->config['auth_url'],
            'HTTP Code' => $tokenHttpCode,
            'cURL Error' => $tokenError ?: 'None',
            'Raw Response' => $tokenResponse
        ], '--- Received Auth Response ---');

        if ($tokenError) throw new Exception("Auth cURL Error: " . $tokenError);
        
        $tokenData = json_decode($tokenResponse, true);
        
        if ($tokenHttpCode >= 400 || !is_array($tokenData) || !isset($tokenData['access_token'])) {
            $errorMsg = 'Failed to obtain access token.';
            if (is_array($tokenData) && isset($tokenData['error_description'])) {
                $errorMsg = $tokenData['error_description'];
            } elseif (!is_array($tokenData) && !empty($tokenResponse)) {
                $errorMsg = "Invalid response from auth server. Check credentials and external token.";
            }
            throw new Exception("Auth API Error on '{$this->config['auth_url']}' (HTTP $tokenHttpCode): " . $errorMsg);
        }
        
        debug_log('Successfully obtained access token.');
        return $tokenData['access_token'];
    }

    public function syncAllAccounts(): int {
        $accessToken = $this->getAuthToken();
        
        debug_log('Starting bulk balance enquiry...');
        $allAccounts = [];
        $page = 0;
        $totalPages = 1;
        $balanceUrl = rtrim($this->config['api_account_url'], '/') . '/bulk-balance';

        do {
            $balancePayload = [
                'header' => ['userId' => $this->config['username']],
                'page' => $page,
                'size' => 100
            ];
            $apiHeaders = ['Authorization: Bearer ' . $accessToken];
            $balanceData = $this->makeApiRequest($balanceUrl, 'POST', $balancePayload, $apiHeaders);

            if (isset($balanceData['payload']['content'])) {
                $count = count($balanceData['payload']['content']);
                debug_log("Page {$page} contained {$count} accounts.");
                $allAccounts = array_merge($allAccounts, $balanceData['payload']['content']);
                $totalPages = $balanceData['payload']['page']['totalPages'] ?? $totalPages;
            } else {
                if ($page === 0) throw new Exception("API response missing 'content' payload.");
            }
            $page++;
        } while ($page < $totalPages);
        debug_log('Finished fetching all pages from bulk balance API. Total accounts fetched: ' . count($allAccounts));

        $syncedCount = 0;
        if (!empty($allAccounts)) {
            $this->db->beginTransaction();
            $sql = "INSERT OR REPLACE INTO bank_accounts (cus_cif_no, cus_name, account_no, account_type, account_status, account_currency, curr_account_balance) 
                    VALUES (:cus_cif_no, :cus_name, :account_no, :account_type, :account_status, :account_currency, :curr_account_balance)";
            $stmt = $this->db->prepare($sql);

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
            $this->db->commit();
        }
        debug_log("Sync complete. Saved {$syncedCount} 'FX TRADE ACCOUNT' type accounts to the database.");
        return $syncedCount;
    }

    private function makeApiRequest($url, $method = 'GET', $payload = null, $headers = []) {
        $ch = curl_init();
        if ($ch === false) {
            throw new Exception("Failed to initialize cURL.");
        }
        $defaultHeaders = ['Content-Type: application/json'];
        $allHeaders = array_merge($defaultHeaders, $headers);

        $loggableHeaders = [];
        foreach ($allHeaders as $header) {
            if (stripos($header, 'Authorization:') === 0) {
                $parts = explode(' ', $header);
                $loggableHeaders[] = $parts[0] . ' Bearer ' . substr($parts[2] ?? '', 0, 8) . '...';
            } else {
                $loggableHeaders[] = $header;
            }
        }
        debug_log([
            'URL' => $url,
            'Method' => $method,
            'Headers' => $loggableHeaders,
            'Payload' => $payload
        ], '--- Sending API Request ---');
        
        $options = [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 60,
            CURLOPT_HTTPHEADER => $allHeaders,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false,
        ];

        if ($method === 'POST') {
            $options[CURLOPT_POST] = true;
            if ($payload) {
                $options[CURLOPT_POSTFIELDS] = json_encode($payload);
            }
        }
        
        curl_setopt_array($ch, $options);
        
        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        debug_log([
            'URL' => $url,
            'HTTP Code' => $http_code,
            'cURL Error' => $error ?: 'None',
            'Raw Response' => $response
        ], '--- Received API Response ---');

        if ($error) {
            throw new Exception("cURL Error: " . $error);
        }
        
        $responseData = json_decode($response, true);

        if ($http_code >= 400) {
            $errorMessage = $responseData['result']['resultMsg'] ?? $responseData['error_description'] ?? 'API request failed.';
            throw new Exception("API Error on '{$url}' (HTTP {$http_code}): " . $errorMessage);
        }

        return $responseData;
    }
}
