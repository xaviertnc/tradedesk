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
 * @version 1.0 - INIT - 09 Jul 2025 - Initial commit.
 */

class BatchService
{
  private $pdo;


  public function __construct( PDO $pdo )
  {
    $this->pdo = $pdo;
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
      // 1. Create a new batch record
      $stmt = $this->pdo->prepare(
        "INSERT INTO batches (batch_uid, status, total_trades, created_at) VALUES (?, 'PENDING', ?, ?)"
      );
      $stmt->execute( [ $batch_uid, $totalTrades, $now ] );
      $batchId = $this->pdo->lastInsertId();

      // 2. Prepare statement for inserting trades
      $tradeStmt = $this->pdo->prepare(
        "INSERT INTO trades (batch_id, client_id, amount_zar, status, created_at)
         VALUES (?, ?, ?, 'PENDING', ?)"
      );

      // 3. Prepare statement for finding client by CIF
      $clientStmt = $this->pdo->prepare( "SELECT id FROM clients WHERE cif_number = ?" );

      // 4. Insert each trade from the CSV
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
          $tradeStmt->execute( [ $batchId, $clientId, $amountZar, $now ] );
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

} // BatchService
