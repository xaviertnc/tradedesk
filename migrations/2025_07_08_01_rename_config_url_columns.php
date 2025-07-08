<?php
// php/migrations/2025_07_08_01_rename_config_url_columns.php

return function(PDO $db) {
    debug_log('Starting migration to rename URL columns in config table...');

    // Begin transaction
    $db->beginTransaction();

    try {
        // 1. Rename the existing table
        $db->exec("ALTER TABLE config RENAME TO config_old");
        debug_log('Renamed config to config_old');

        // 2. Create the new table with the correct schema
        $db->exec("CREATE TABLE config (
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
        )");
        debug_log('Created new config table with correct schema');

        // 3. Copy data from the old table to the new one, mapping the columns
        // Note: This assumes the old columns were named 'api_base_url' and 'bulk_balance_url'
        $db->exec("INSERT INTO config (
            id, api_trading_url, api_account_url, auth_url, client_id, client_secret, 
            username, password, api_external_token, otc_rate, access_token, token_expiry
        ) 
        SELECT 
            id, api_base_url, bulk_balance_url, auth_url, client_id, client_secret, 
            username, password, api_external_token, otc_rate, access_token, token_expiry 
        FROM config_old");
        debug_log('Copied data from old table to new table');

        // 4. Drop the old table
        $db->exec("DROP TABLE config_old");
        debug_log('Dropped old config table');

        // Commit the transaction
        $db->commit();
        debug_log('Migration completed successfully.');

    } catch (Exception $e) {
        // If anything goes wrong, roll back the transaction
        $db->rollBack();
        debug_log('Migration failed, rolling back changes. Error: ' . $e->getMessage(), 'MIGRATION_ERROR', 1, 'ERROR');
        // Re-throw the exception to be caught by the main handler
        throw $e;
    }
};
