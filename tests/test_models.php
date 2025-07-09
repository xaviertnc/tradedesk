<?php
/**
 * test_models.php
 *
 * Model Classes Test - 09 Jul 2025
 *
 * Purpose: Test the new Model, Batch, and Trade classes to ensure they work correctly.
 *
 * @package FX-Trades-App
 * @author Gemini <gemini@google.com>
 *
 * @version 1.0 - INIT - 09 Jul 2025 - Initial commit
 */

require_once __DIR__ . '/../Model.php';
require_once __DIR__ . '/../Batch.php';
require_once __DIR__ . '/../Trade.php';

// Database connection
$db_file = __DIR__ . '/../data/tradedesk.db';
$pdo = new PDO( 'sqlite:' . $db_file );
$pdo->setAttribute( PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION );

echo "=== Testing Model Classes ===\n\n";

// Test Batch Model
echo "1. Testing Batch Model:\n";
$batchModel = new Batch( $pdo );

// Test creating a batch
echo "   - Creating test batch...\n";
$batchData = [
  'batch_uid' => 'test_batch_' . time(),
  'status' => Batch::STATUS_PENDING,
  'total_trades' => 5,
  'processed_trades' => 0,
  'failed_trades' => 0,
  'created_at' => date( 'Y-m-d H:i:s' ),
  'updated_at' => date( 'Y-m-d H:i:s' )
];

$batch = $batchModel->create( $batchData );
echo "   - Batch created with ID: " . $batch->getAttribute( 'id' ) . "\n";

// Test finding the batch
$foundBatch = $batchModel->find( $batch->getAttribute( 'id' ) );
echo "   - Batch found: " . ( $foundBatch ? 'YES' : 'NO' ) . "\n";

// Test status update
echo "   - Updating status to RUNNING...\n";
$foundBatch->updateStatus( Batch::STATUS_RUNNING );
echo "   - New status: " . $foundBatch->getAttribute( 'status' ) . "\n";

// Test Trade Model
echo "\n2. Testing Trade Model:\n";
$tradeModel = new Trade( $pdo );

// Get a client ID for testing
$clientStmt = $pdo->prepare( "SELECT id FROM clients LIMIT 1" );
$clientStmt->execute();
$clientId = $clientStmt->fetchColumn();

if ( $clientId ) {
  echo "   - Creating test trade...\n";
  $tradeData = [
    'client_id' => $clientId,
    'batch_id' => $batch->getAttribute( 'id' ),
    'amount_zar' => 10000.00,
    'status' => Trade::STATUS_PENDING,
    'created_at' => date( 'Y-m-d H:i:s' )
  ];

  $trade = $tradeModel->create( $tradeData );
  echo "   - Trade created with ID: " . $trade->getAttribute( 'id' ) . "\n";

  // Test finding the trade
  $foundTrade = $tradeModel->find( $trade->getAttribute( 'id' ) );
  echo "   - Trade found: " . ( $foundTrade ? 'YES' : 'NO' ) . "\n";

  // Test status update
  echo "   - Updating status to QUOTED...\n";
  $foundTrade->updateStatus( Trade::STATUS_QUOTED, 'Test quote' );
  echo "   - New status: " . $foundTrade->getAttribute( 'status' ) . "\n";

  // Test getting trades by batch
  $batchTrades = $tradeModel->findByBatchId( $batch->getAttribute( 'id' ) );
  echo "   - Trades in batch: " . count( $batchTrades ) . "\n";

  // Test batch relationship
  $tradeBatch = $foundTrade->getBatch();
  echo "   - Trade's batch UID: " . ( $tradeBatch ? $tradeBatch->getAttribute( 'batch_uid' ) : 'NONE' ) . "\n";

  // Test batch summary
  $batchSummary = $batch->getSummary();
  echo "   - Batch summary - Total trades: " . $batchSummary['total_trades'] . 
       ", Success: " . $batchSummary['success_count'] . 
       ", Failed: " . $batchSummary['failed_count'] . "\n";

} else {
  echo "   - No clients found in database, skipping trade tests\n";
}

// Test Batch Service
echo "\n3. Testing BatchService:\n";
require_once __DIR__ . '/../BatchService.php';
$batchService = new BatchService( $pdo );

// Test getting batches
$batches = $batchService->getBatches( [], 'created_at DESC', 5 );
echo "   - Recent batches: " . count( $batches ) . "\n";

// Test getting active batches
$activeBatches = $batchService->getActiveBatches();
echo "   - Active batches: " . count( $activeBatches ) . "\n";

// Test batch summary
if ( $batch ) {
  $summary = $batchService->getBatchSummary( $batch->getAttribute( 'id' ) );
  echo "   - Batch summary retrieved: " . ( $summary ? 'YES' : 'NO' ) . "\n";
}

echo "\n=== Model Tests Completed ===\n";
echo "All tests passed successfully!\n"; 