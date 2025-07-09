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

    return $batch->updateProgress();
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

} // BatchService
