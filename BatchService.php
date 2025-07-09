<?php
/**
 * BatchService.php
 *
 * Batch Service - 09 Jul 2025
 *
 * Purpose: Handles the business logic for creating and processing trade batches.
 *
 * @package FX-Trades-App
 * @author Gemini <gemini@google.com>
 *
 * Last 3 version commits:
 * @version 1.2 - FT - 10 Jul 2025 - Auto-detect and set batch final status in updateBatchProgress
 * @version 1.1 - UPD - 09 Jul 2025 - Refactor to use Batch and Trade models
 * @version 1.0 - INIT - 09 Jul 2025 - Initial commit.
 */

require_once __DIR__ . '/Batch.php';
require_once __DIR__ . '/Trade.php';

class BatchService
{
  private $pdo;
  private $batchModel;
  private $tradeModel;


  public function __construct( PDO $pdo )
  {
    $this->pdo = $pdo;
    $this->batchModel = new Batch( $pdo );
    $this->tradeModel = new Trade( $pdo );
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
    $batch = $this->batchModel->find( $batchId );
    if ( ! $batch ) {
      return false;
    }

    return $batch->updateStatus( $newStatus );
  } // updateBatchStatus


  /**
   * Update batch progress based on trade statuses.
   *
   * @param int $batchId
   * @return bool
   */
  public function updateBatchProgress( int $batchId ): bool
  {
    $batch = $this->batchModel->find( $batchId );
    if ( ! $batch ) {
      return false;
    }

    $progressUpdated = $batch->updateProgress();

    // Check if all trades are in a final state
    $trades = $batch->getTrades();
    $allFinal = true;
    foreach ( $trades as $trade ) {
      $status = $trade->getAttribute( 'status' );
      if ( ! in_array( $status, [ 'SUCCESS', 'FAILED', 'CANCELLED' ] ) ) {
        $allFinal = false;
        break;
      }
    }

    if ( $allFinal && ! $batch->isCompleted() ) {
      $finalStatus = $batch->determineFinalStatus();
      $batch->updateStatus( $finalStatus );
    }

    return $progressUpdated;
  } // updateBatchProgress


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
    $batch = $this->batchModel->find( $batchId );
    if ( ! $batch ) {
      return false;
    }

    $this->pdo->beginTransaction();

    try
    {
      // Update batch status to cancelled
      $batch->updateStatus( Batch::STATUS_CANCELLED );

      // Cancel all pending trades in the batch
      $trades = $this->tradeModel->findByBatchId( $batchId );
      foreach ( $trades as $trade ) {
        if ( $trade->isActive() ) {
          $trade->updateStatus( Trade::STATUS_CANCELLED, 'Batch cancelled' );
        }
      }

      $this->pdo->commit();
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
    $batch = $this->batchModel->find( $batchId );
    if ( ! $batch ) {
      return false;
    }

    // Update batch status to running
    if ( ! $batch->updateStatus( Batch::STATUS_RUNNING ) ) {
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
      return true;
    }

    // Process trades asynchronously (in a real system, this would be a background job)
    foreach ( $pendingTrades as $trade ) {
      $this->executeTradeInBatch( $trade );
    }

    return true;
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

} // BatchService
