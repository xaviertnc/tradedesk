<?php
/**
 * test_batch.php
 *
 * Batch Functionality Test - 10 Jul 2025
 *
 * Purpose: Test script to verify batch creation and management functionality.
 *
 * @package FX-Trades-App
 * @author Gemini <gemini@google.com>
 *
 * @version 1.0 - INIT - 10 Jul 2025 - Initial commit.
 */

require_once __DIR__ . '/BatchService.php';

// Test database connection
try {
  $db_file = 'data' . DIRECTORY_SEPARATOR . 'tradedesk.db';
  $pdo = new PDO( 'sqlite:' . $db_file );
  $pdo->setAttribute( PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION );
  echo "✓ Database connection successful\n";
} catch ( PDOException $e ) {
  echo "✗ Database connection failed: " . $e->getMessage() . "\n";
  exit( 1 );
}

// Test BatchService
try {
  $batchService = new BatchService( $pdo );
  echo "✓ BatchService instantiated successfully\n";
} catch ( Exception $e ) {
  echo "✗ BatchService instantiation failed: " . $e->getMessage() . "\n";
  exit( 1 );
}

// Test batch creation from CSV
try {
  if ( file_exists( 'sample_batch.csv' ) ) {
    $batchId = $batchService->createBatchFromCsv( 'sample_batch.csv' );
    echo "✓ Batch created successfully with ID: {$batchId}\n";
    
    // Verify batch was created
    $stmt = $pdo->prepare( "SELECT * FROM batches WHERE id = ?" );
    $stmt->execute( [ $batchId ] );
    $batch = $stmt->fetch();
    
    if ( $batch ) {
      echo "✓ Batch record found in database\n";
      echo "  - Batch UID: {$batch['batch_uid']}\n";
      echo "  - Status: {$batch['status']}\n";
      echo "  - Total Trades: {$batch['total_trades']}\n";
    } else {
      echo "✗ Batch record not found in database\n";
    }
    
    // Check trades were created
    $stmt = $pdo->prepare( "SELECT COUNT(*) FROM trades WHERE batch_id = ?" );
    $stmt->execute( [ $batchId ] );
    $tradeCount = $stmt->fetchColumn();
    echo "✓ {$tradeCount} trades created for batch\n";
    
  } else {
    echo "⚠ Sample CSV file not found, skipping batch creation test\n";
  }
} catch ( Exception $e ) {
  echo "✗ Batch creation test failed: " . $e->getMessage() . "\n";
}

// Test API endpoints
echo "\nTesting API endpoints...\n";

// Test GET /api/batches
$context = stream_context_create( [
  'http' => [
    'method' => 'GET',
    'header' => 'Content-Type: application/json'
  ]
] );

$response = file_get_contents( 'http://localhost:8000/api.php?action=get_batches', false, $context );
if ( $response !== false ) {
  $data = json_decode( $response, true );
  if ( $data && isset( $data['success'] ) && $data['success'] ) {
    echo "✓ GET /api/batches endpoint working\n";
    echo "  - Found " . count( $data['batches'] ) . " batches\n";
  } else {
    echo "✗ GET /api/batches endpoint failed\n";
  }
} else {
  echo "✗ Could not connect to API server\n";
}

echo "\nBatch functionality test completed!\n"; 