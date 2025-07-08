<?php
// CapitecApiService.php

/**
 * Handles all business logic for communicating with the Capitec Forex endpoints.
 */
class CapitecApiService {
    private PDO $db;
    private array $config;
    private HttpClientService $httpClient;

    public function __construct(PDO $db, HttpClientService $httpClient) {
        $this->db = $db;
        $this->httpClient = $httpClient;
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

        $tokenData = $this->httpClient->sendRequest(
            $this->config['auth_url'],
            'POST',
            $tokenPayload,
            $authHeaders,
            false // Not a JSON payload
        );
        
        if (!isset($tokenData['access_token'])) {
             $errorMsg = $tokenData['error_description'] ?? 'Failed to obtain access token.';
             throw new Exception("Auth API Error: " . $errorMsg);
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
            $balanceData = $this->httpClient->sendRequest($balanceUrl, 'POST', $balancePayload, $apiHeaders);

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
}
