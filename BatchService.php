<?php
/**
 * BatchService.php
 *
 * Batch Processing Service - 28 Jun 2025 ( Start Date )
 *
 * Purpose: Handles batch creation, processing, and management with concurrent support,
 *          real-time updates, and comprehensive search capabilities.
 *
 * @package TradeDesk Services
 *
 * @author Assistant <assistant@example.com>
 *
 * Last 3 version commits:
 * @version 1.0 - INIT - 28 Jun 2025 - Initial commit
 * @version 1.1 - UPD - 10 Jul 2025 - Added concurrent batch handling
 * @version 1.2 - UPD - 10 Jul 2025 - Added real-time updates and search functionality
 */

require_once __DIR__ . '/Batch.php';
require_once __DIR__ . '/Trade.php';

class BatchService
{
  private $pdo;
  private $batchModel;
  private $tradeModel;
  private $processId;
  private $lockTimeout = 300; // 5 minutes


  public function __construct( PDO $pdo )
  {
    $this->pdo = $pdo;
    $this->batchModel = new Batch( $pdo );
    $this->tradeModel = new Trade( $pdo );
    $this->processId = uniqid( 'process_', true );
  } // __construct


  /**
   * Creates a new batch and associated trades from a CSV file.
   *
   * @param string $csvFilePath The path to the uploaded CSV file.
   * @return int The ID of the newly created batch.
   * @throws Exception If the file is invalid or if the database transaction fails.
   */
  public function createBatchFromCsv( string $csvFilePath ): int
  {
    if ( ! file_exists( $csvFilePath ) || ! is_readable( $csvFilePath ) )
    {
      throw new Exception( 'CSV file not found or is not readable.' );
    }

    $trades = array_map( 'str_getcsv', file( $csvFilePath ) );
    array_shift( $trades ); // Remove header row

    if ( empty( $trades ) )
    {
      throw new Exception( 'CSV file is empty or has no data rows.' );
    }

    $totalTrades = count( $trades );
    $batch_uid = 'batch_' . time();
    $now = date( 'Y-m-d H:i:s' );

    $this->pdo->beginTransaction();

    try
    {
      // 1. Create a new batch record using the model
      $batchData = [
        'batch_uid' => $batch_uid,
        'status' => Batch::STATUS_PENDING,
        'total_trades' => $totalTrades,
        'processed_trades' => 0,
        'failed_trades' => 0,
        'created_at' => $now,
        'updated_at' => $now
      ];

      $batch = $this->batchModel->create( $batchData );
      $batchId = $batch->getAttribute( 'id' );

      // 2. Prepare statement for finding client by CIF
      $clientStmt = $this->pdo->prepare( "SELECT id FROM clients WHERE cif_number = ?" );

      // 3. Insert each trade from the CSV using the model
      foreach ( $trades as $trade )
      {
        // Assuming CSV format: Client CIF, Amount ZAR
        $cifNumber = $trade[0] ?? null;
        $amountZar = $trade[1] ?? null;

        if ( empty( $cifNumber ) || ! is_numeric( $amountZar ) || $amountZar <= 0 )
        {
          continue; // Skip invalid rows
        }

        // Find client by CIF number
        $clientStmt->execute( [ $cifNumber ] );
        $clientId = $clientStmt->fetchColumn();

        if ( $clientId )
        {
          $tradeData = [
            'batch_id' => $batchId,
            'client_id' => $clientId,
            'amount_zar' => $amountZar,
            'status' => Trade::STATUS_PENDING,
            'created_at' => $now
          ];

          $this->tradeModel->create( $tradeData );
        }
        else
        {
          // Log unknown CIF numbers but continue processing
          error_log( "Unknown CIF number in CSV: {$cifNumber}" );
        }
      }

      $this->pdo->commit();

      return (int)$batchId;
    }
    catch ( Exception $e )
    {
      $this->pdo->rollBack();
      throw new Exception( 'Failed to create batch: ' . $e->getMessage() );
    } // try-catch

  } // createBatchFromCsv


  /**
   * Get all batches with optional filtering.
   *
   * @param array $filters
   * @param string $orderBy
   * @param int $limit
   * @return array
   */
  public function getBatches( array $filters = [], string $orderBy = 'created_at DESC', int $limit = 0 ): array
  {
    return $this->batchModel->findAll( $filters, $orderBy, $limit );
  } // getBatches


  /**
   * Get a specific batch by ID.
   *
   * @param int $batchId
   * @return Batch|null
   */
  public function getBatch( int $batchId )
  {
    return $this->batchModel->find( $batchId );
  } // getBatch


  /**
   * Get a batch by its UID.
   *
   * @param string $batchUid
   * @return Batch|null
   */
  public function getBatchByUid( string $batchUid )
  {
    return $this->batchModel->findByUid( $batchUid );
  } // getBatchByUid


  /**
   * Get active batches (pending or running).
   *
   * @return array
   */
  public function getActiveBatches(): array
  {
    return $this->batchModel->getActiveBatches();
  } // getActiveBatches


  /**
   * Get recent completed batches.
   *
   * @param int $limit
   * @return array
   */
  public function getRecentCompletedBatches( int $limit = 10 ): array
  {
    return $this->batchModel->getRecentCompletedBatches( $limit );
  } // getRecentCompletedBatches


  /**
   * Update batch status.
   *
   * @param int $batchId
   * @param string $newStatus
   * @return bool
   */
  public function updateBatchStatus( int $batchId, string $newStatus ): bool
  {
    $sql = "UPDATE batches SET 
              status = ?, 
              updated_at = ?,
              completed_at = ?
            WHERE id = ?";
    
    $completedAt = in_array( $newStatus, [ 'success', 'partial_success', 'failed' ] ) 
      ? date( 'Y-m-d H:i:s' ) 
      : null;
    
    $stmt = $this->pdo->prepare( $sql );
    $result = $stmt->execute( [ $newStatus, date( 'Y-m-d H:i:s' ), $completedAt, $batchId ] );
    
    // Send status change notification
    $this->sendBatchNotification( $batchId, 'status_change' );
    
    return $result;
  } // updateBatchStatus


  /**
   * Update batch progress and detect completion
   */
  public function updateBatchProgress( int $batchId )
  {
    $batch = $this->getBatch( $batchId );
    if ( ! $batch ) {
      return false;
    }
    
    // Get trade counts
    $sql = "SELECT 
              COUNT(*) as total,
              SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed,
              SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) as failed,
              SUM(CASE WHEN status IN ('pending', 'running') THEN 1 ELSE 0 END) as pending
            FROM trades 
            WHERE batch_id = ?";
    
    $stmt = $this->pdo->prepare( $sql );
    $stmt->execute( [ $batchId ] );
    $counts = $stmt->fetch( PDO::FETCH_ASSOC );
    
    // Update batch with new counts
    $sql = "UPDATE batches SET 
              processed_trades = ?, 
              failed_trades = ?,
              updated_at = ?
            WHERE id = ?";
    
    $stmt = $this->pdo->prepare( $sql );
    $stmt->execute( [ 
      $counts['completed'], 
      $counts['failed'], 
      date( 'Y-m-d H:i:s' ), 
      $batchId 
    ] );
    
    // Check if batch is complete
    $oldStatus = $batch['status'];
    $newStatus = $this->determineBatchStatus( $counts );
    
    if ( $newStatus !== $oldStatus ) {
      $this->updateBatchStatus( $batchId, $newStatus );
      
      // Send notifications for status changes
      if ( in_array( $newStatus, [ 'success', 'partial_success', 'failed' ] ) ) {
        $this->sendBatchNotification( $batchId, 'completion' );
      } elseif ( $newStatus === 'running' && $oldStatus === 'pending' ) {
        $this->sendBatchNotification( $batchId, 'started' );
      }
    }
    
    return [
      'total_trades' => $counts['total'],
      'processed_trades' => $counts['completed'],
      'failed_trades' => $counts['failed'],
      'pending_trades' => $counts['pending'],
      'status' => $newStatus,
      'is_complete' => in_array( $newStatus, [ 'success', 'partial_success', 'failed' ] )
    ];
  } // updateBatchProgress


  /**
   * Determine batch status based on trade counts
   */
  public function determineBatchStatus( $counts )
  {
    $total = $counts['total'];
    $completed = $counts['completed'];
    $failed = $counts['failed'];
    $pending = $counts['pending'];
    
    if ( $total === 0 ) {
      return 'pending';
    }
    
    if ( $pending > 0 ) {
      return 'running';
    }
    
    if ( $failed === $total ) {
      return 'failed';
    }
    
    if ( $completed === $total ) {
      return 'success';
    }
    
    if ( $completed > 0 && $failed > 0 ) {
      return 'partial_success';
    }
    
    return 'pending';
  } // determineBatchStatus


  /**
   * Get trades for a specific batch.
   *
   * @param int $batchId
   * @return array
   */
  public function getBatchTrades( int $batchId ): array
  {
    return $this->tradeModel->findByBatchId( $batchId );
  } // getBatchTrades


  /**
   * Get batch summary with trade statistics.
   *
   * @param int $batchId
   * @return array|null
   */
  public function getBatchSummary( int $batchId )
  {
    $batch = $this->batchModel->find( $batchId );
    if ( ! $batch ) {
      return null;
    }

    return $batch->getSummary();
  } // getBatchSummary


  /**
   * Cancel a batch and all its pending trades.
   *
   * @param int $batchId
   * @return bool
   */
  public function cancelBatch( int $batchId ): bool
  {
    $batch = $this->getBatch( $batchId );
    if ( ! $batch || ! in_array( $batch['status'], [ 'pending', 'running' ] ) ) {
      return false;
    }

    $this->pdo->beginTransaction();

    try
    {
      // Update batch status to cancelled
      $this->updateBatchStatus( $batchId, 'cancelled' );

      // Cancel all pending trades in the batch
      $sql = "UPDATE trades SET 
                status = 'cancelled', 
                status_message = 'Batch cancelled',
                updated_at = ?
              WHERE batch_id = ? AND status IN ('pending', 'running')";
      
      $stmt = $this->pdo->prepare( $sql );
      $stmt->execute( [ date( 'Y-m-d H:i:s' ), $batchId ] );

      $this->pdo->commit();
      
      // Send cancellation notification
      $this->sendBatchNotification( $batchId, 'cancelled' );
      
      return true;
    }
    catch ( Exception $e )
    {
      $this->pdo->rollBack();
      error_log( "Failed to cancel batch {$batchId}: " . $e->getMessage() );
      return false;
    } // try-catch

  } // cancelBatch


  /**
   * Delete a completed batch and all its trades.
   *
   * @param int $batchId
   * @return bool
   */
  public function deleteBatch( int $batchId ): bool
  {
    $batch = $this->batchModel->find( $batchId );
    if ( ! $batch ) {
      return false;
    }

    // Only allow deletion of completed batches
    if ( ! $batch->isCompleted() ) {
      throw new Exception( 'Cannot delete active batch. Cancel it first.' );
    }

    $this->pdo->beginTransaction();

    try
    {
      // Delete all trades in the batch
      $trades = $this->tradeModel->findByBatchId( $batchId );
      foreach ( $trades as $trade ) {
        $this->tradeModel->delete( $trade->getAttribute( 'id' ) );
      }

      // Delete the batch
      $this->batchModel->delete( $batchId );

      $this->pdo->commit();
      return true;
    }
    catch ( Exception $e )
    {
      $this->pdo->rollBack();
      error_log( "Failed to delete batch {$batchId}: " . $e->getMessage() );
      return false;
    } // try-catch

  } // deleteBatch


  /**
   * Get batch progress information.
   *
   * @param int $batchId
   * @return array|null
   */
  public function getBatchProgress( int $batchId )
  {
    $batch = $this->batchModel->find( $batchId );
    if ( ! $batch ) {
      return null;
    }

    // Update progress first
    $this->updateBatchProgress( $batchId );
    
    // Get fresh batch data
    $batch = $this->batchModel->find( $batchId );
    
    return [
      'batch_id' => $batchId,
      'status' => $batch->getAttribute( 'status' ),
      'total_trades' => $batch->getAttribute( 'total_trades' ),
      'processed_trades' => $batch->getAttribute( 'processed_trades' ),
      'failed_trades' => $batch->getAttribute( 'failed_trades' ),
      'progress_percentage' => $batch->getProgressPercentage(),
      'is_completed' => $batch->isCompleted(),
      'is_active' => $batch->isActive(),
      'updated_at' => $batch->getAttribute( 'updated_at' )
    ];
  } // getBatchProgress


  /**
   * Get batch results with detailed trade information.
   *
   * @param int $batchId
   * @return array|null
   */
  public function getBatchResults( int $batchId )
  {
    $batch = $this->batchModel->find( $batchId );
    if ( ! $batch ) {
      return null;
    }

    $trades = $batch->getTrades();
    $tradeSummaries = [];

    foreach ( $trades as $trade ) {
      $tradeSummaries[] = $trade->getSummary();
    }

    return [
      'batch' => $batch->getSummary(),
      'trades' => $tradeSummaries,
      'progress' => $this->getBatchProgress( $batchId )
    ];
  } // getBatchResults


  /**
   * Start async batch processing.
   *
   * @param int $batchId
   * @return bool
   */
  public function runBatchAsync( int $batchId ): bool
  {
    // Try to acquire lock for this batch
    if ( ! $this->acquireBatchLock( $batchId ) ) {
      return false; // Batch is already being processed by another process
    }

    try
    {
      $batch = $this->batchModel->find( $batchId );
      if ( ! $batch ) {
        $this->releaseBatchLock( $batchId );
        return false;
      }

      // Update batch status to running and set started_at
      $updateData = [
        'status' => Batch::STATUS_RUNNING,
        'started_at' => date( 'Y-m-d H:i:s' )
      ];
      
      if ( ! $batch->update( $batchId, $updateData ) ) {
        $this->releaseBatchLock( $batchId );
        return false;
      }

      // Get all pending trades in the batch
      $trades = $this->tradeModel->findByBatchId( $batchId );
      $pendingTrades = array_filter( $trades, function( $trade ) {
        return $trade->getAttribute( 'status' ) === Trade::STATUS_PENDING;
      } );

      if ( empty( $pendingTrades ) ) {
        // No pending trades, mark batch as completed
        $this->updateBatchProgress( $batchId );
        $this->releaseBatchLock( $batchId );
        return true;
      }

      // Process trades with concurrency control
      $this->processTradesWithConcurrency( $batchId, $pendingTrades );

      return true;
    }
    catch ( Exception $e )
    {
      // Release lock on error
      $this->releaseBatchLock( $batchId );
      error_log( "Error processing batch {$batchId}: " . $e->getMessage() );
      return false;
    } // try-catch

  } // runBatchAsync


  /**
   * Execute a single trade within batch context.
   *
   * @param Trade $trade
   * @return bool
   */
  private function executeTradeInBatch( Trade $trade ): bool
  {
    try
    {
      // Step 1: Update trade status to executing
      $trade->updateStatus( Trade::STATUS_EXECUTING, 'Processing trade' );

      // Step 2: Get client information
      $client = $trade->getClient();
      if ( ! $client ) {
        $trade->updateStatus( Trade::STATUS_FAILED, 'Client not found' );
        $this->onTradeCompleted( $trade );
        return false;
      }

      // Step 3: Create quote (simulate API call)
      $quoteResult = $this->createQuoteForTrade( $trade, $client );
      if ( ! $quoteResult['success'] ) {
        $trade->updateStatus( Trade::STATUS_FAILED, $quoteResult['message'] );
        $this->onTradeCompleted( $trade );
        return false;
      }

      // Step 4: Update trade with quote information
      $trade->updateQuote( $quoteResult['quote_id'], $quoteResult['quote_rate'] );
      $trade->updateStatus( Trade::STATUS_QUOTED, 'Quote created successfully' );

      // Step 5: Execute the trade (simulate API call)
      $executionResult = $this->executeTrade( $trade, $client );
      if ( ! $executionResult['success'] ) {
        $trade->updateStatus( Trade::STATUS_FAILED, $executionResult['message'] );
        $this->onTradeCompleted( $trade );
        return false;
      }

      // Step 6: Update trade with execution information
      $trade->updateExecution( $executionResult['bank_trxn_id'], $executionResult['deal_ref'] );
      $trade->updateStatus( Trade::STATUS_SUCCESS, 'Trade executed successfully' );

      // Step 7: Notify batch of trade completion
      $this->onTradeCompleted( $trade );

      return true;
    }
    catch ( Exception $e )
    {
      $trade->updateStatus( Trade::STATUS_FAILED, 'Exception: ' . $e->getMessage() );
      $this->onTradeCompleted( $trade );
      return false;
    } // try-catch

  } // executeTradeInBatch


  /**
   * Create quote for a trade (simulated).
   *
   * @param Trade $trade
   * @param array $client
   * @return array
   */
  private function createQuoteForTrade( Trade $trade, array $client ): array
  {
    // Simulate API call to create quote
    // In a real implementation, this would call the Capitec CreateQuote API
    
    $amountZar = $trade->getAttribute( 'amount_zar' );
    
    // Simulate some validation
    if ( $amountZar <= 0 ) {
      return [ 'success' => false, 'message' => 'Invalid amount' ];
    }

    if ( empty( $client['zar_account'] ) ) {
      return [ 'success' => false, 'message' => 'Client has no ZAR account' ];
    }

    // Simulate quote creation
    $quoteId = 'quote_' . time() . '_' . $trade->getAttribute( 'id' );
    $quoteRate = 18.50 + ( rand( -50, 50 ) / 1000 ); // Simulate rate variation

    return [
      'success' => true,
      'quote_id' => $quoteId,
      'quote_rate' => $quoteRate,
      'message' => 'Quote created successfully'
    ];
  } // createQuoteForTrade


  /**
   * Execute a trade (simulated).
   *
   * @param Trade $trade
   * @param array $client
   * @return array
   */
  private function executeTrade( Trade $trade, array $client ): array
  {
    // Simulate API call to execute trade
    // In a real implementation, this would call the Capitec BookQuotedDeal API
    
    $quoteId = $trade->getAttribute( 'quote_id' );
    $quoteRate = $trade->getAttribute( 'quote_rate' );
    
    if ( empty( $quoteId ) || empty( $quoteRate ) ) {
      return [ 'success' => false, 'message' => 'Missing quote information' ];
    }

    // Simulate trade execution with 90% success rate
    if ( rand( 1, 100 ) <= 90 ) {
      $bankTrxnId = 'trxn_' . time() . '_' . $trade->getAttribute( 'id' );
      $dealRef = 'deal_' . time() . '_' . $trade->getAttribute( 'id' );

      return [
        'success' => true,
        'bank_trxn_id' => $bankTrxnId,
        'deal_ref' => $dealRef,
        'message' => 'Trade executed successfully'
      ];
    } else {
      return [ 'success' => false, 'message' => 'Trade execution failed (simulated)' ];
    }
  } // executeTrade


  /**
   * Handle trade completion callback.
   *
   * @param Trade $trade
   */
  private function onTradeCompleted( Trade $trade ): void
  {
    $batchId = $trade->getAttribute( 'batch_id' );
    if ( $batchId ) {
      // Update batch progress
      $this->updateBatchProgress( $batchId );
    }
  } // onTradeCompleted


  /**
   * Get pending trades that need processing.
   *
   * @return array
   */
  public function getPendingTrades(): array
  {
    return $this->tradeModel->getPendingTrades();
  } // getPendingTrades


  /**
   * Process all pending trades in batches.
   *
   * @param int $batchSize
   * @return int Number of trades processed
   */
  public function processPendingTrades( int $batchSize = 10 ): int
  {
    $pendingTrades = $this->getPendingTrades();
    $processedCount = 0;

    foreach ( $pendingTrades as $trade ) {
      if ( $processedCount >= $batchSize ) {
        break;
      }

      if ( $this->executeTradeInBatch( $trade ) ) {
        $processedCount++;
      }
    }

    return $processedCount;
  } // processPendingTrades


  /**
   * Get detailed error information for a batch.
   *
   * @param int $batchId
   * @return array|null
   */
  public function getBatchErrors( int $batchId )
  {
    $batch = $this->batchModel->find( $batchId );
    if ( ! $batch ) {
      return null;
    }

    // Get failed trades
    $failedTrades = $this->tradeModel->findAll( 
      [ 'batch_id' => $batchId, 'status' => Trade::STATUS_FAILED ], 
      'created_at ASC' 
    );

    // Group errors by type
    $errorSummary = [];
    foreach ( $failedTrades as $trade ) {
      $errorMessage = $trade->getAttribute( 'status_message' ) ?? 'Unknown Error';
      if ( ! isset( $errorSummary[$errorMessage] ) ) {
        $errorSummary[$errorMessage] = [
          'count' => 0,
          'trades' => []
        ];
      }
      $errorSummary[$errorMessage]['count']++;
      $errorSummary[$errorMessage]['trades'][] = $trade->getSummary();
    }

    return [
      'batch' => $batch->getSummary(),
      'failed_trades' => array_map( function( $trade ) {
        return $trade->getSummary();
      }, $failedTrades ),
      'error_summary' => $errorSummary,
      'total_failed' => count( $failedTrades )
    ];
  } // getBatchErrors


  /**
   * Acquire a lock for batch processing.
   *
   * @param int $batchId
   * @return bool
   */
  private function acquireBatchLock( int $batchId ): bool
  {
    try
    {
      $now = date( 'Y-m-d H:i:s' );
      $timeout = date( 'Y-m-d H:i:s', time() + $this->lockTimeout );

      // Try to acquire lock using atomic update
      $stmt = $this->pdo->prepare( "
        UPDATE batches 
        SET locked_at = ?, locked_by = ?, lock_timeout = ?
        WHERE id = ? 
        AND (locked_at IS NULL OR locked_at < ? OR locked_by = ?)
      " );

      $result = $stmt->execute( [
        $now,
        $this->processId,
        $timeout,
        $batchId,
        $now, // Check for expired locks
        $this->processId // Allow re-locking by same process
      ] );

      return $stmt->rowCount() > 0;
    }
    catch ( Exception $e )
    {
      error_log( "Error acquiring batch lock: " . $e->getMessage() );
      return false;
    } // try-catch

  } // acquireBatchLock


  /**
   * Release a batch lock.
   *
   * @param int $batchId
   * @return bool
   */
  private function releaseBatchLock( int $batchId ): bool
  {
    try
    {
      $stmt = $this->pdo->prepare( "
        UPDATE batches 
        SET locked_at = NULL, locked_by = NULL, lock_timeout = NULL
        WHERE id = ? AND locked_by = ?
      " );

      return $stmt->execute( [ $batchId, $this->processId ] );
    }
    catch ( Exception $e )
    {
      error_log( "Error releasing batch lock: " . $e->getMessage() );
      return false;
    } // try-catch

  } // releaseBatchLock


  /**
   * Process trades with concurrency control.
   *
   * @param int $batchId
   * @param array $pendingTrades
   */
  private function processTradesWithConcurrency( int $batchId, array $pendingTrades ): void
  {
    $batch = $this->batchModel->find( $batchId );
    $maxConcurrent = $batch->getAttribute( 'max_concurrent_trades' ) ?: 5;
    $currentConcurrent = 0;

    foreach ( $pendingTrades as $trade )
    {
      // Check if we can process more trades concurrently
      while ( $currentConcurrent >= $maxConcurrent )
      {
        // Wait a bit and check again
        usleep( 100000 ); // 100ms
        $this->updateBatchConcurrencyCount( $batchId );
        $batch = $this->batchModel->find( $batchId );
        $currentConcurrent = $batch->getAttribute( 'current_concurrent_trades' ) ?: 0;
      }

      // Increment concurrent count
      $this->incrementBatchConcurrencyCount( $batchId );
      $currentConcurrent++;

      // Process trade asynchronously (in real system, this would be a background job)
      $this->executeTradeInBatch( $trade );

      // Decrement concurrent count
      $this->decrementBatchConcurrencyCount( $batchId );
      $currentConcurrent--;
    }
  } // processTradesWithConcurrency


  /**
   * Update batch concurrency count.
   *
   * @param int $batchId
   */
  private function updateBatchConcurrencyCount( int $batchId ): void
  {
    try
    {
      $stmt = $this->pdo->prepare( "
        UPDATE batches 
        SET current_concurrent_trades = (
          SELECT COUNT(*) 
          FROM trades 
          WHERE batch_id = ? AND status IN (?, ?)
        )
        WHERE id = ?
      " );

      $stmt->execute( [
        $batchId,
        Trade::STATUS_EXECUTING,
        Trade::STATUS_QUOTED,
        $batchId
      ] );
    }
    catch ( Exception $e )
    {
      error_log( "Error updating batch concurrency count: " . $e->getMessage() );
    } // try-catch

  } // updateBatchConcurrencyCount


  /**
   * Increment batch concurrency count.
   *
   * @param int $batchId
   */
  private function incrementBatchConcurrencyCount( int $batchId ): void
  {
    try
    {
      $stmt = $this->pdo->prepare( "
        UPDATE batches 
        SET current_concurrent_trades = COALESCE(current_concurrent_trades, 0) + 1
        WHERE id = ?
      " );

      $stmt->execute( [ $batchId ] );
    }
    catch ( Exception $e )
    {
      error_log( "Error incrementing batch concurrency count: " . $e->getMessage() );
    } // try-catch

  } // incrementBatchConcurrencyCount


  /**
   * Decrement batch concurrency count.
   *
   * @param int $batchId
   */
  private function decrementBatchConcurrencyCount( int $batchId ): void
  {
    try
    {
      $stmt = $this->pdo->prepare( "
        UPDATE batches 
        SET current_concurrent_trades = GREATEST(COALESCE(current_concurrent_trades, 0) - 1, 0)
        WHERE id = ?
      " );

      $stmt->execute( [ $batchId ] );
    }
    catch ( Exception $e )
    {
      error_log( "Error decrementing batch concurrency count: " . $e->getMessage() );
    } // try-catch

  } // decrementBatchConcurrencyCount


  /**
   * Get next batch from queue for processing.
   *
   * @return int|null
   */
  public function getNextBatchFromQueue(): ?int
  {
    try
    {
      $stmt = $this->pdo->prepare( "
        SELECT id FROM batches 
        WHERE status = ? 
        AND (locked_at IS NULL OR locked_at < ?)
        ORDER BY priority DESC, queue_position ASC, created_at ASC
        LIMIT 1
      " );

      $stmt->execute( [ Batch::STATUS_PENDING, date( 'Y-m-d H:i:s' ) ] );
      $result = $stmt->fetchColumn();

      return $result ? (int)$result : null;
    }
    catch ( Exception $e )
    {
      error_log( "Error getting next batch from queue: " . $e->getMessage() );
      return null;
    } // try-catch

  } // getNextBatchFromQueue


  /**
   * Clean up expired locks.
   */
  public function cleanupExpiredLocks(): int
  {
    try
    {
      $stmt = $this->pdo->prepare( "
        UPDATE batches 
        SET locked_at = NULL, locked_by = NULL, lock_timeout = NULL
        WHERE locked_at < ?
      " );

      $stmt->execute( [ date( 'Y-m-d H:i:s' ) ] );
      return $stmt->rowCount();
    }
    catch ( Exception $e )
    {
      error_log( "Error cleaning up expired locks: " . $e->getMessage() );
      return 0;
    } // try-catch

  } // cleanupExpiredLocks


  /**
   * Set batch priority.
   *
   * @param int $batchId
   * @param int $priority (1-10, higher is more important)
   * @return bool
   */
  public function setBatchPriority( int $batchId, int $priority ): bool
  {
    try
    {
      $priority = max( 1, min( 10, $priority ) ); // Clamp between 1-10
      
      $stmt = $this->pdo->prepare( "
        UPDATE batches 
        SET priority = ?
        WHERE id = ?
      " );

      return $stmt->execute( [ $priority, $batchId ] );
    }
    catch ( Exception $e )
    {
      error_log( "Error setting batch priority: " . $e->getMessage() );
      return false;
    } // try-catch

  } // setBatchPriority


  /**
   * Get batches that are currently locked.
   *
   * @return array
   */
  public function getLockedBatches(): array
  {
    try
    {
      $stmt = $this->pdo->prepare( "
        SELECT id, batch_uid, locked_at, locked_by, lock_timeout, status
        FROM batches 
        WHERE locked_at IS NOT NULL
        ORDER BY locked_at ASC
      " );

      $stmt->execute();
      return $stmt->fetchAll( PDO::FETCH_ASSOC );
    }
    catch ( Exception $e )
    {
      error_log( "Error getting locked batches: " . $e->getMessage() );
      return [];
    } // try-catch

  } // getLockedBatches


  /**
   * Get recent batch updates for real-time notifications
   */
  public function getRecentUpdates( $sinceTimestamp )
  {
    $sql = "SELECT 
              b.id,
              b.batch_uid,
              b.status,
              b.total_trades,
              b.processed_trades,
              b.failed_trades,
              b.updated_at,
              'batch_update' as update_type
            FROM batches b 
            WHERE b.updated_at > ? 
            ORDER BY b.updated_at DESC";
    
    $stmt = $this->pdo->prepare( $sql );
    $stmt->execute( [ date( 'Y-m-d H:i:s', $sinceTimestamp ) ] );
    $updates = $stmt->fetchAll( PDO::FETCH_ASSOC );
    
    // Add trade completion updates
    $sql = "SELECT 
              t.id,
              t.batch_id,
              t.status,
              t.updated_at,
              'trade_update' as update_type
            FROM trades t 
            WHERE t.updated_at > ? AND t.batch_id IS NOT NULL
            ORDER BY t.updated_at DESC";
    
    $stmt = $this->pdo->prepare( $sql );
    $stmt->execute( [ date( 'Y-m-d H:i:s', $sinceTimestamp ) ] );
    $tradeUpdates = $stmt->fetchAll( PDO::FETCH_ASSOC );
    
    return array_merge( $updates, $tradeUpdates );
  } // getRecentUpdates


  /**
   * Search batches with advanced filtering and pagination
   */
  public function searchBatches( $filters = [], $page = 1, $limit = 20, $sortBy = 'created_at', $sortOrder = 'DESC' )
  {
    $whereConditions = [];
    $params = [];
    
    // Status filter
    if ( isset( $filters['status'] ) && $filters['status'] ) {
      $whereConditions[] = "b.status = ?";
      $params[] = $filters['status'];
    }
    
    // Date range filters
    if ( isset( $filters['date_from'] ) && $filters['date_from'] ) {
      $whereConditions[] = "b.created_at >= ?";
      $params[] = $filters['date_from'] . ' 00:00:00';
    }
    
    if ( isset( $filters['date_to'] ) && $filters['date_to'] ) {
      $whereConditions[] = "b.created_at <= ?";
      $params[] = $filters['date_to'] . ' 23:59:59';
    }
    
    // Build WHERE clause
    $whereClause = '';
    if ( ! empty( $whereConditions ) ) {
      $whereClause = 'WHERE ' . implode( ' AND ', $whereConditions );
    }
    
    // Validate sort parameters
    $allowedSortFields = [ 'created_at', 'updated_at', 'status', 'total_trades', 'processed_trades' ];
    $sortBy = in_array( $sortBy, $allowedSortFields ) ? $sortBy : 'created_at';
    $sortOrder = strtoupper( $sortOrder ) === 'ASC' ? 'ASC' : 'DESC';
    
    // Count total records
    $countSql = "SELECT COUNT(*) as total FROM batches b $whereClause";
    $stmt = $this->pdo->prepare( $countSql );
    $stmt->execute( $params );
    $totalRecords = $stmt->fetch( PDO::FETCH_ASSOC )['total'];
    
    // Calculate pagination
    $offset = ( $page - 1 ) * $limit;
    $totalPages = ceil( $totalRecords / $limit );
    
    // Get paginated results
    $sql = "SELECT 
              b.*,
              COUNT(t.id) as actual_trades
            FROM batches b 
            LEFT JOIN trades t ON b.id = t.batch_id
            $whereClause
            GROUP BY b.id
            ORDER BY b.$sortBy $sortOrder
            LIMIT ? OFFSET ?";
    
    $params[] = $limit;
    $params[] = $offset;
    
    $stmt = $this->pdo->prepare( $sql );
    $stmt->execute( $params );
    $batches = $stmt->fetchAll( PDO::FETCH_ASSOC );
    
    return [
      'batches' => $batches,
      'pagination' => [
        'current_page' => $page,
        'total_pages' => $totalPages,
        'total_records' => $totalRecords,
        'limit' => $limit,
        'has_next' => $page < $totalPages,
        'has_prev' => $page > 1
      ],
      'filters' => $filters,
      'sort' => [
        'field' => $sortBy,
        'order' => $sortOrder
      ]
    ];
  } // searchBatches


  /**
   * Send batch completion notification
   */
  public function sendBatchNotification( $batchId, $type = 'completion' )
  {
    $batch = $this->getBatch( $batchId );
    if ( ! $batch ) {
      return false;
    }
    
    $notification = [
      'type' => 'batch_notification',
      'batch_id' => $batchId,
      'batch_uid' => $batch['batch_uid'],
      'notification_type' => $type,
      'status' => $batch['status'],
      'total_trades' => $batch['total_trades'],
      'processed_trades' => $batch['processed_trades'],
      'failed_trades' => $batch['failed_trades'],
      'timestamp' => time()
    ];
    
    // Store notification in database for persistence
    $sql = "INSERT INTO batch_notifications (batch_id, type, data, created_at) VALUES (?, ?, ?, ?)";
    $stmt = $this->pdo->prepare( $sql );
    $stmt->execute( [ $batchId, $type, json_encode( $notification ), date( 'Y-m-d H:i:s' ) ] );
    
    return $notification;
  } // sendBatchNotification


  /**
   * Get pending notifications
   */
  public function getPendingNotifications( $limit = 50 )
  {
    $sql = "SELECT * FROM batch_notifications WHERE sent_at IS NULL ORDER BY created_at DESC LIMIT ?";
    $stmt = $this->pdo->prepare( $sql );
    $stmt->execute( [ $limit ] );
    return $stmt->fetchAll( PDO::FETCH_ASSOC );
  } // getPendingNotifications


  /**
   * Mark notification as sent
   */
  public function markNotificationSent( $notificationId )
  {
    $sql = "UPDATE batch_notifications SET sent_at = ? WHERE id = ?";
    $stmt = $this->pdo->prepare( $sql );
    return $stmt->execute( [ date( 'Y-m-d H:i:s' ), $notificationId ] );
  } // markNotificationSent

} // BatchService
