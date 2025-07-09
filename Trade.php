<?php
/**
 * Trade.php
 *
 * Trade Model - 09 Jul 2025
 *
 * Purpose: Represents individual trades with status tracking and batch relationships.
 *
 * @package FX-Trades-App
 * @author Gemini <gemini@google.com>
 *
 * @version 1.0 - INIT - 09 Jul 2025 - Initial commit
 */

require_once __DIR__ . '/Model.php';

class Trade extends Model
{
  protected $table = 'trades';
  protected $fillable = [
    'client_id',
    'batch_id',
    'status',
    'status_message',
    'quote_id',
    'quote_rate',
    'amount_zar',
    'bank_trxn_id',
    'deal_ref',
    'created_at'
  ];

  // Status constants
  const STATUS_PENDING = 'PENDING';
  const STATUS_QUOTED = 'QUOTED';
  const STATUS_EXECUTING = 'EXECUTING';
  const STATUS_SUCCESS = 'SUCCESS';
  const STATUS_FAILED = 'FAILED';
  const STATUS_CANCELLED = 'CANCELLED';

  // Valid status transitions
  private static $validTransitions = [
    self::STATUS_PENDING => [ self::STATUS_QUOTED, self::STATUS_FAILED, self::STATUS_CANCELLED ],
    self::STATUS_QUOTED => [ self::STATUS_EXECUTING, self::STATUS_FAILED, self::STATUS_CANCELLED ],
    self::STATUS_EXECUTING => [ self::STATUS_SUCCESS, self::STATUS_FAILED, self::STATUS_CANCELLED ],
    self::STATUS_SUCCESS => [],
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
      self::STATUS_QUOTED,
      self::STATUS_EXECUTING,
      self::STATUS_SUCCESS,
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
   * Update trade status with validation.
   *
   * @param string $newStatus
   * @param string $statusMessage
   * @return bool
   */
  public function updateStatus( string $newStatus, string $statusMessage = '' ): bool
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
      'status_message' => $statusMessage
    ];

    $success = $this->update( $this->getAttribute( 'id' ), $updateData );
    
    if ( $success ) {
      $this->setAttribute( 'status', $newStatus );
      $this->setAttribute( 'status_message', $statusMessage );
    }

    return $success;
  } // updateStatus


  /**
   * Get the batch this trade belongs to.
   *
   * @return Batch|null
   */
  public function getBatch()
  {
    $batchId = $this->getAttribute( 'batch_id' );
    if ( ! $batchId ) {
      return null;
    }

    $batchModel = new Batch( $this->pdo );
    return $batchModel->find( (int)$batchId );
  } // getBatch


  /**
   * Get the client for this trade.
   *
   * @return array|null
   */
  public function getClient()
  {
    $clientId = $this->getAttribute( 'client_id' );
    if ( ! $clientId ) {
      return null;
    }

    $stmt = $this->pdo->prepare( "SELECT * FROM clients WHERE id = ?" );
    $stmt->execute( [ $clientId ] );
    return $stmt->fetch( PDO::FETCH_ASSOC );
  } // getClient


  /**
   * Check if trade is completed (any final status).
   *
   * @return bool
   */
  public function isCompleted(): bool
  {
    $status = $this->getAttribute( 'status' );
    return in_array( $status, [ 
      self::STATUS_SUCCESS, 
      self::STATUS_FAILED, 
      self::STATUS_CANCELLED 
    ] );
  } // isCompleted


  /**
   * Check if trade is active (not completed).
   *
   * @return bool
   */
  public function isActive(): bool
  {
    return ! $this->isCompleted();
  } // isActive


  /**
   * Get trade summary for display.
   *
   * @return array
   */
  public function getSummary(): array
  {
    $client = $this->getClient();
    $batch = $this->getBatch();

    return [
      'id' => $this->getAttribute( 'id' ),
      'client_name' => $client['name'] ?? 'Unknown',
      'client_cif' => $client['cif_number'] ?? 'Unknown',
      'amount_zar' => $this->getAttribute( 'amount_zar' ),
      'status' => $this->getAttribute( 'status' ),
      'status_message' => $this->getAttribute( 'status_message' ),
      'quote_id' => $this->getAttribute( 'quote_id' ),
      'quote_rate' => $this->getAttribute( 'quote_rate' ),
      'bank_trxn_id' => $this->getAttribute( 'bank_trxn_id' ),
      'deal_ref' => $this->getAttribute( 'deal_ref' ),
      'batch_id' => $this->getAttribute( 'batch_id' ),
      'batch_uid' => $batch ? $batch->getAttribute( 'batch_uid' ) : null,
      'created_at' => $this->getAttribute( 'created_at' )
    ];
  } // getSummary


  /**
   * Find trades by batch ID.
   *
   * @param int $batchId
   * @return array
   */
  public function findByBatchId( int $batchId ): array
  {
    return $this->findAll( [ 'batch_id' => $batchId ], 'created_at ASC' );
  } // findByBatchId


  /**
   * Find trades by status.
   *
   * @param string $status
   * @return array
   */
  public function findByStatus( string $status ): array
  {
    return $this->findAll( [ 'status' => $status ], 'created_at DESC' );
  } // findByStatus


  /**
   * Find trades by client ID.
   *
   * @param int $clientId
   * @return array
   */
  public function findByClientId( int $clientId ): array
  {
    return $this->findAll( [ 'client_id' => $clientId ], 'created_at DESC' );
  } // findByClientId


  /**
   * Get trades that need processing (pending status).
   *
   * @return array
   */
  public function getPendingTrades(): array
  {
    return $this->findByStatus( self::STATUS_PENDING );
  } // getPendingTrades


  /**
   * Get completed trades (success, failed, or cancelled).
   *
   * @return array
   */
  public function getCompletedTrades(): array
  {
    $completedStatuses = [ self::STATUS_SUCCESS, self::STATUS_FAILED, self::STATUS_CANCELLED ];
    $placeholders = str_repeat( '?,', count( $completedStatuses ) - 1 ) . '?';
    $sql = "SELECT * FROM {$this->table} WHERE status IN ({$placeholders}) ORDER BY created_at DESC";
    
    $stmt = $this->pdo->prepare( $sql );
    $stmt->execute( $completedStatuses );
    $results = $stmt->fetchAll( PDO::FETCH_ASSOC );

    return array_map( [ $this, 'createFromArray' ], $results );
  } // getCompletedTrades


  /**
   * Update trade with quote information.
   *
   * @param string $quoteId
   * @param float $quoteRate
   * @return bool
   */
  public function updateQuote( string $quoteId, float $quoteRate ): bool
  {
    $updateData = [
      'quote_id' => $quoteId,
      'quote_rate' => $quoteRate
    ];

    return $this->update( $this->getAttribute( 'id' ), $updateData );
  } // updateQuote


  /**
   * Update trade with execution information.
   *
   * @param string $bankTrxnId
   * @param string $dealRef
   * @return bool
   */
  public function updateExecution( string $bankTrxnId, string $dealRef ): bool
  {
    $updateData = [
      'bank_trxn_id' => $bankTrxnId,
      'deal_ref' => $dealRef
    ];

    return $this->update( $this->getAttribute( 'id' ), $updateData );
  } // updateExecution

} // Trade 