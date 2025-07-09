<?php
/**
 * Batch.php
 *
 * Batch Model - 09 Jul 2025
 *
 * Purpose: Represents a batch of trades with status tracking and lifecycle management.
 *
 * @package FX-Trades-App
 * @author Gemini <gemini@google.com>
 *
 * @version 1.0 - INIT - 09 Jul 2025 - Initial commit
 */

require_once __DIR__ . '/Model.php';

class Batch extends Model
{
  protected $table = 'batches';
  protected $fillable = [
    'batch_uid',
    'status',
    'total_trades',
    'processed_trades',
    'failed_trades',
    'created_at',
    'updated_at'
  ];

  // Status constants
  const STATUS_PENDING = 'PENDING';
  const STATUS_RUNNING = 'RUNNING';
  const STATUS_SUCCESS = 'SUCCESS';
  const STATUS_PARTIAL_SUCCESS = 'PARTIAL_SUCCESS';
  const STATUS_FAILED = 'FAILED';
  const STATUS_CANCELLED = 'CANCELLED';

  // Valid status transitions
  private static $validTransitions = [
    self::STATUS_PENDING => [ self::STATUS_RUNNING, self::STATUS_CANCELLED ],
    self::STATUS_RUNNING => [ self::STATUS_SUCCESS, self::STATUS_PARTIAL_SUCCESS, self::STATUS_FAILED, self::STATUS_CANCELLED ],
    self::STATUS_SUCCESS => [],
    self::STATUS_PARTIAL_SUCCESS => [],
    self::STATUS_FAILED => [],
    self::STATUS_CANCELLED => []
  ];


  /**
   * Get all valid statuses.
   *
   * @return array
   */
  public static function getValidStatuses(): array
  {
    return [
      self::STATUS_PENDING,
      self::STATUS_RUNNING,
      self::STATUS_SUCCESS,
      self::STATUS_PARTIAL_SUCCESS,
      self::STATUS_FAILED,
      self::STATUS_CANCELLED
    ];
  } // getValidStatuses


  /**
   * Check if a status transition is valid.
   *
   * @param string $fromStatus
   * @param string $toStatus
   * @return bool
   */
  public static function isValidTransition( string $fromStatus, string $toStatus ): bool
  {
    return in_array( $toStatus, self::$validTransitions[$fromStatus] ?? [] );
  } // isValidTransition


  /**
   * Update batch status with validation.
   *
   * @param string $newStatus
   * @return bool
   */
  public function updateStatus( string $newStatus ): bool
  {
    if ( ! in_array( $newStatus, self::getValidStatuses() ) ) {
      throw new InvalidArgumentException( "Invalid status: {$newStatus}" );
    }

    $currentStatus = $this->getAttribute( 'status' );
    if ( ! self::isValidTransition( $currentStatus, $newStatus ) ) {
      throw new InvalidArgumentException( "Invalid status transition from {$currentStatus} to {$newStatus}" );
    }

    $updateData = [
      'status' => $newStatus,
      'updated_at' => date( 'Y-m-d H:i:s' )
    ];

    // If transitioning to a final status, set completed_at
    if ( in_array( $newStatus, [ self::STATUS_SUCCESS, self::STATUS_PARTIAL_SUCCESS, self::STATUS_FAILED, self::STATUS_CANCELLED ] ) ) {
      $updateData['completed_at'] = date( 'Y-m-d H:i:s' );
    }

    $success = $this->update( $this->getAttribute( 'id' ), $updateData );
    
    if ( $success ) {
      $this->setAttribute( 'status', $newStatus );
      $this->setAttribute( 'updated_at', $updateData['updated_at'] );
      
      if ( isset( $updateData['completed_at'] ) ) {
        $this->setAttribute( 'completed_at', $updateData['completed_at'] );
      }
    }

    return $success;
  } // updateStatus


  /**
   * Get all trades associated with this batch.
   *
   * @return array
   */
  public function getTrades(): array
  {
    $tradeModel = new Trade( $this->pdo );
    return $tradeModel->findAll( [ 'batch_id' => $this->getAttribute( 'id' ) ], 'created_at ASC' );
  } // getTrades


  /**
   * Get trades by status.
   *
   * @param string $status
   * @return array
   */
  public function getTradesByStatus( string $status ): array
  {
    $tradeModel = new Trade( $this->pdo );
    return $tradeModel->findAll( 
      [ 'batch_id' => $this->getAttribute( 'id' ), 'status' => $status ], 
      'created_at ASC' 
    );
  } // getTradesByStatus


  /**
   * Get batch progress percentage.
   *
   * @return float
   */
  public function getProgressPercentage(): float
  {
    $totalTrades = $this->getAttribute( 'total_trades' );
    if ( $totalTrades <= 0 ) {
      return 0.0;
    }

    $processedTrades = $this->getAttribute( 'processed_trades' );
    $failedTrades = $this->getAttribute( 'failed_trades' );
    
    return round( ( ( $processedTrades + $failedTrades ) / $totalTrades ) * 100, 2 );
  } // getProgressPercentage


  /**
   * Check if batch is completed (any final status).
   *
   * @return bool
   */
  public function isCompleted(): bool
  {
    $status = $this->getAttribute( 'status' );
    return in_array( $status, [ 
      self::STATUS_SUCCESS, 
      self::STATUS_PARTIAL_SUCCESS, 
      self::STATUS_FAILED, 
      self::STATUS_CANCELLED 
    ] );
  } // isCompleted


  /**
   * Check if batch is active (running or pending).
   *
   * @return bool
   */
  public function isActive(): bool
  {
    $status = $this->getAttribute( 'status' );
    return in_array( $status, [ self::STATUS_PENDING, self::STATUS_RUNNING ] );
  } // isActive


  /**
   * Update batch progress based on trade statuses.
   *
   * @return bool
   */
  public function updateProgress(): bool
  {
    $trades = $this->getTrades();
    $totalTrades = count( $trades );
    $processedTrades = 0;
    $failedTrades = 0;

    foreach ( $trades as $trade ) {
      $tradeStatus = $trade->getAttribute( 'status' );
      if ( in_array( $tradeStatus, [ 'SUCCESS', 'FAILED', 'CANCELLED' ] ) ) {
        $processedTrades++;
        if ( $tradeStatus === 'FAILED' ) {
          $failedTrades++;
        }
      }
    }

    $updateData = [
      'total_trades' => $totalTrades,
      'processed_trades' => $processedTrades,
      'failed_trades' => $failedTrades,
      'updated_at' => date( 'Y-m-d H:i:s' )
    ];

    return $this->update( $this->getAttribute( 'id' ), $updateData );
  } // updateProgress


  /**
   * Determine final batch status based on trade results.
   *
   * @return string
   */
  public function determineFinalStatus(): string
  {
    $trades = $this->getTrades();
    $totalTrades = count( $trades );
    
    if ( $totalTrades === 0 ) {
      return self::STATUS_FAILED;
    }

    $successCount = 0;
    $failedCount = 0;

    foreach ( $trades as $trade ) {
      $status = $trade->getAttribute( 'status' );
      if ( $status === 'SUCCESS' ) {
        $successCount++;
      } elseif ( $status === 'FAILED' ) {
        $failedCount++;
      }
    }

    if ( $successCount === $totalTrades ) {
      return self::STATUS_SUCCESS;
    } elseif ( $failedCount === $totalTrades ) {
      return self::STATUS_FAILED;
    } elseif ( $successCount > 0 ) {
      return self::STATUS_PARTIAL_SUCCESS;
    } else {
      return self::STATUS_FAILED;
    }
  } // determineFinalStatus


  /**
   * Get batch summary statistics.
   *
   * @return array
   */
  public function getSummary(): array
  {
    $trades = $this->getTrades();
    $totalTrades = count( $trades );
    $successCount = 0;
    $failedCount = 0;
    $pendingCount = 0;
    $totalAmount = 0;

    foreach ( $trades as $trade ) {
      $status = $trade->getAttribute( 'status' );
      $amount = $trade->getAttribute( 'amount_zar' ) ?? 0;
      $totalAmount += $amount;

      switch ( $status ) {
        case 'SUCCESS':
          $successCount++;
          break;
        case 'FAILED':
          $failedCount++;
          break;
        default:
          $pendingCount++;
          break;
      }
    }

    return [
      'total_trades' => $totalTrades,
      'success_count' => $successCount,
      'failed_count' => $failedCount,
      'pending_count' => $pendingCount,
      'total_amount' => $totalAmount,
      'progress_percentage' => $this->getProgressPercentage(),
      'status' => $this->getAttribute( 'status' ),
      'created_at' => $this->getAttribute( 'created_at' ),
      'updated_at' => $this->getAttribute( 'updated_at' )
    ];
  } // getSummary


  /**
   * Find batch by batch_uid.
   *
   * @param string $batchUid
   * @return static|null
   */
  public function findByUid( string $batchUid )
  {
    $stmt = $this->pdo->prepare( "SELECT * FROM {$this->table} WHERE batch_uid = ?" );
    $stmt->execute( [ $batchUid ] );
    $data = $stmt->fetch( PDO::FETCH_ASSOC );

    if ( ! $data ) {
      return null;
    }

    return $this->createFromArray( $data );
  } // findByUid


  /**
   * Get active batches (pending or running).
   *
   * @return array
   */
  public function getActiveBatches(): array
  {
    return $this->findAll( 
      [ 'status' => [ self::STATUS_PENDING, self::STATUS_RUNNING ] ], 
      'created_at DESC' 
    );
  } // getActiveBatches


  /**
   * Get recent completed batches.
   *
   * @param int $limit
   * @return array
   */
  public function getRecentCompletedBatches( int $limit = 10 ): array
  {
    $completedStatuses = [ 
      self::STATUS_SUCCESS, 
      self::STATUS_PARTIAL_SUCCESS, 
      self::STATUS_FAILED, 
      self::STATUS_CANCELLED 
    ];
    
    $placeholders = str_repeat( '?,', count( $completedStatuses ) - 1 ) . '?';
    $sql = "SELECT * FROM {$this->table} WHERE status IN ({$placeholders}) ORDER BY created_at DESC LIMIT ?";
    
    $params = array_merge( $completedStatuses, [ $limit ] );
    $stmt = $this->pdo->prepare( $sql );
    $stmt->execute( $params );
    $results = $stmt->fetchAll( PDO::FETCH_ASSOC );

    return array_map( [ $this, 'createFromArray' ], $results );
  } // getRecentCompletedBatches

} // Batch 