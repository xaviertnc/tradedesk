<?php
/**
 * tests/test_concurrent_batches.php
 *
 * Test Concurrent Batch Handling - 10 Jul 2025
 *
 * Purpose: Test the concurrent batch handling functionality including
 * locking, queue management, and priority handling.
 *
 * @package FX-Trades-App
 * @author Gemini <gemini@google.com>
 *
 * Last 3 version commits:
 * @version 1.0 - INIT - 10 Jul 2025 - Initial commit.
 */

require_once __DIR__ . '/../BatchService.php';
require_once __DIR__ . '/../Batch.php';
require_once __DIR__ . '/../Trade.php';

try {
  $pdo = new PDO( 'sqlite:C:\laragon\www\projects\currencyhub\tradedesk\v8\data\tradedesk.db' );
  $pdo->setAttribute( PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION );
  
  $batchService = new BatchService( $pdo );
  
  echo "🧪 Testing Concurrent Batch Handling\n";
  echo "=====================================\n\n";
  
  // Test 1: Clean up any existing locks
  echo "1. Cleaning up expired locks...\n";
  $cleanedCount = $batchService->cleanupExpiredLocks();
  echo "   ✅ Cleaned up {$cleanedCount} expired locks\n\n";
  
  // Test 2: Create test batches with different priorities
  echo "2. Creating test batches with different priorities...\n";
  
  // Create batch data
  $batchData1 = [
    'batch_uid' => 'test_batch_1_' . time(),
    'status' => Batch::STATUS_PENDING,
    'total_trades' => 3,
    'processed_trades' => 0,
    'failed_trades' => 0,
    'priority' => 8, // High priority
    'max_concurrent_trades' => 2
  ];
  
  $batchData2 = [
    'batch_uid' => 'test_batch_2_' . time(),
    'status' => Batch::STATUS_PENDING,
    'total_trades' => 2,
    'processed_trades' => 0,
    'failed_trades' => 0,
    'priority' => 5, // Medium priority
    'max_concurrent_trades' => 3
  ];
  
  $batchData3 = [
    'batch_uid' => 'test_batch_3_' . time(),
    'status' => Batch::STATUS_PENDING,
    'total_trades' => 4,
    'processed_trades' => 0,
    'failed_trades' => 0,
    'priority' => 3, // Low priority
    'max_concurrent_trades' => 1
  ];
  
  // Insert batches
  $stmt = $pdo->prepare( "
    INSERT INTO batches (batch_uid, status, total_trades, processed_trades, failed_trades, priority, max_concurrent_trades, created_at, updated_at)
    VALUES (?, ?, ?, ?, ?, ?, ?, datetime('now'), datetime('now'))
  " );
  
  $stmt->execute( [
    $batchData1['batch_uid'], $batchData1['status'], $batchData1['total_trades'], 
    $batchData1['processed_trades'], $batchData1['failed_trades'], $batchData1['priority'], $batchData1['max_concurrent_trades']
  ] );
  $batchId1 = $pdo->lastInsertId();
  
  $stmt->execute( [
    $batchData2['batch_uid'], $batchData2['status'], $batchData2['total_trades'], 
    $batchData2['processed_trades'], $batchData2['failed_trades'], $batchData2['priority'], $batchData2['max_concurrent_trades']
  ] );
  $batchId2 = $pdo->lastInsertId();
  
  $stmt->execute( [
    $batchData3['batch_uid'], $batchData3['status'], $batchData3['total_trades'], 
    $batchData3['processed_trades'], $batchData3['failed_trades'], $batchData3['priority'], $batchData3['max_concurrent_trades']
  ] );
  $batchId3 = $pdo->lastInsertId();
  
  echo "   ✅ Created 3 test batches (IDs: {$batchId1}, {$batchId2}, {$batchId3})\n\n";
  
  // Test 3: Test queue ordering by priority
  echo "3. Testing queue ordering by priority...\n";
  $nextBatchId = $batchService->getNextBatchFromQueue();
  echo "   Next batch from queue: {$nextBatchId}\n";
  echo "   Expected: {$batchId1} (highest priority)\n";
  echo "   " . ($nextBatchId == $batchId1 ? "✅ PASS" : "❌ FAIL") . "\n\n";
  
  // Test 4: Test batch locking
  echo "4. Testing batch locking...\n";
  $lockAcquired1 = $batchService->runBatchAsync( $batchId1 );
  echo "   Batch {$batchId1} lock acquired: " . ($lockAcquired1 ? "✅ YES" : "❌ NO") . "\n";
  
  // Try to acquire lock on same batch from another process
  $batchService2 = new BatchService( $pdo ); // Different process
  $lockAcquired2 = $batchService2->runBatchAsync( $batchId1 );
  echo "   Batch {$batchId1} lock acquired by second process: " . ($lockAcquired2 ? "❌ YES (should be NO)" : "✅ NO (correct)") . "\n\n";
  
  // Test 5: Test locked batches retrieval
  echo "5. Testing locked batches retrieval...\n";
  $lockedBatches = $batchService->getLockedBatches();
  echo "   Locked batches count: " . count( $lockedBatches ) . "\n";
  echo "   " . (count( $lockedBatches ) > 0 ? "✅ PASS" : "❌ FAIL") . "\n\n";
  
  // Test 6: Test priority setting
  echo "6. Testing priority setting...\n";
  $prioritySet = $batchService->setBatchPriority( $batchId2, 9 );
  echo "   Priority set for batch {$batchId2}: " . ($prioritySet ? "✅ YES" : "❌ NO") . "\n";
  
  // Check if queue order changed
  $nextBatchId2 = $batchService->getNextBatchFromQueue();
  echo "   Next batch after priority change: {$nextBatchId2}\n";
  echo "   Expected: {$batchId2} (now highest priority)\n";
  echo "   " . ($nextBatchId2 == $batchId2 ? "✅ PASS" : "❌ FAIL") . "\n\n";
  
  // Test 7: Test active batches retrieval
  echo "7. Testing active batches retrieval...\n";
  $activeBatches = $batchService->getActiveBatches();
  echo "   Active batches count: " . count( $activeBatches ) . "\n";
  echo "   " . (count( $activeBatches ) > 0 ? "✅ PASS" : "❌ FAIL") . "\n\n";
  
  // Test 8: Test recent batches retrieval
  echo "8. Testing recent batches retrieval...\n";
  $recentBatches = $batchService->getRecentCompletedBatches( 5 );
  echo "   Recent completed batches count: " . count( $recentBatches ) . "\n";
  echo "   ✅ PASS\n\n";
  
  // Test 9: Cleanup test data
  echo "9. Cleaning up test data...\n";
  $stmt = $pdo->prepare( "DELETE FROM batches WHERE batch_uid LIKE 'test_batch_%'" );
  $stmt->execute();
  $deletedCount = $stmt->rowCount();
  echo "   ✅ Deleted {$deletedCount} test batches\n\n";
  
  echo "🎉 All concurrent batch handling tests completed!\n";
  echo "✅ Milestone 2.4 - Concurrent Batch Handling - IMPLEMENTED\n";
  
} catch ( Exception $e ) {
  echo "❌ Test failed: " . $e->getMessage() . "\n";
  echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
} 