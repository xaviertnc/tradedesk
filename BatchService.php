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

    $this->pdo->beginTransaction();

    try
    {
      // 1. Create a new batch record
      $stmt = $this->pdo->prepare(
        "INSERT INTO batches (total_trades, status) VALUES (?, 'PENDING')"
      );
      $stmt->execute( [ $totalTrades ] );
      $batchId = $this->pdo->lastInsertId();

      // 2. Prepare statement for inserting trades
      $tradeStmt = $this->pdo->prepare(
        "INSERT INTO trades (batch_id, source_currency, source_amount, destination_currency, status)
         VALUES (?, ?, ?, ?, 'PENDING')"
      );

      // 3. Insert each trade from the CSV
      foreach ( $trades as $trade )
      {
        // Assuming CSV format: Source Currency, Source Amount, Destination Currency
        $sourceCurrency = $trade[0] ?? null;
        $sourceAmount = $trade[1] ?? null;
        $destinationCurrency = $trade[2] ?? null;

        $tradeStmt->execute( [ $batchId, $sourceCurrency, $sourceAmount, $destinationCurrency ] );
      }

      $this->pdo->commit();

      return (int)$batchId;
    }
    catch ( Exception $e )
    {
      $this->pdo->rollBack();
      // In a real app, you'd log this error properly.
      // debug_log( $e->getMessage(), 'Batch Creation Failed' );
      throw new Exception( 'Failed to create batch: ' . $e->getMessage() );
    } // try-catch

  } // createBatchFromCsv

} // BatchService
