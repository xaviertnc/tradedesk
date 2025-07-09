<?php
/**
 * Model.php
 *
 * Base Model Class - 09 Jul 2025
 *
 * Purpose: Provides common database operations and structure for all models.
 *
 * @package FX-Trades-App
 * @author Gemini <gemini@google.com>
 *
 * @version 1.0 - INIT - 09 Jul 2025 - Initial commit
 */

abstract class Model
{
  protected $pdo;
  protected $table;
  protected $primaryKey = 'id';
  protected $fillable = [];
  protected $attributes = [];


  public function __construct( PDO $pdo )
  {
    $this->pdo = $pdo;
  } // __construct


  /**
   * Find a record by its primary key.
   *
   * @param int $id
   * @return static|null
   */
  public function find( int $id )
  {
    $stmt = $this->pdo->prepare( "SELECT * FROM {$this->table} WHERE {$this->primaryKey} = ?" );
    $stmt->execute( [ $id ] );
    $data = $stmt->fetch( PDO::FETCH_ASSOC );

    if ( ! $data ) {
      return null;
    }

    return $this->createFromArray( $data );
  } // find


  /**
   * Find all records with optional conditions.
   *
   * @param array $conditions
   * @param string $orderBy
   * @param int $limit
   * @return array
   */
  public function findAll( array $conditions = [], string $orderBy = '', int $limit = 0 ): array
  {
    $sql = "SELECT * FROM {$this->table}";
    $params = [];

    if ( ! empty( $conditions ) ) {
      $whereClauses = [];
      foreach ( $conditions as $field => $value ) {
        $whereClauses[] = "{$field} = ?";
        $params[] = $value;
      }
      $sql .= " WHERE " . implode( ' AND ', $whereClauses );
    }

    if ( $orderBy ) {
      $sql .= " ORDER BY {$orderBy}";
    }

    if ( $limit > 0 ) {
      $sql .= " LIMIT {$limit}";
    }

    $stmt = $this->pdo->prepare( $sql );
    $stmt->execute( $params );
    $results = $stmt->fetchAll( PDO::FETCH_ASSOC );

    return array_map( [ $this, 'createFromArray' ], $results );
  } // findAll


  /**
   * Create a new record.
   *
   * @param array $data
   * @return static
   */
  public function create( array $data )
  {
    $fillableData = array_intersect_key( $data, array_flip( $this->fillable ) );
    
    $fields = array_keys( $fillableData );
    $placeholders = array_fill( 0, count( $fields ), '?' );
    
    $sql = "INSERT INTO {$this->table} (" . implode( ', ', $fields ) . ") VALUES (" . implode( ', ', $placeholders ) . ")";
    
    $stmt = $this->pdo->prepare( $sql );
    $stmt->execute( array_values( $fillableData ) );
    
    $id = $this->pdo->lastInsertId();
    return $this->find( (int)$id );
  } // create


  /**
   * Update an existing record.
   *
   * @param int $id
   * @param array $data
   * @return bool
   */
  public function update( int $id, array $data ): bool
  {
    $fillableData = array_intersect_key( $data, array_flip( $this->fillable ) );
    
    if ( empty( $fillableData ) ) {
      return false;
    }
    
    $setClauses = [];
    $params = [];
    
    foreach ( $fillableData as $field => $value ) {
      $setClauses[] = "{$field} = ?";
      $params[] = $value;
    }
    
    $params[] = $id;
    
    $sql = "UPDATE {$this->table} SET " . implode( ', ', $setClauses ) . " WHERE {$this->primaryKey} = ?";
    $stmt = $this->pdo->prepare( $sql );
    
    return $stmt->execute( $params );
  } // update


  /**
   * Delete a record.
   *
   * @param int $id
   * @return bool
   */
  public function delete( int $id ): bool
  {
    $stmt = $this->pdo->prepare( "DELETE FROM {$this->table} WHERE {$this->primaryKey} = ?" );
    return $stmt->execute( [ $id ] );
  } // delete


  /**
   * Get attribute value.
   *
   * @param string $key
   * @return mixed
   */
  public function getAttribute( string $key )
  {
    return $this->attributes[$key] ?? null;
  } // getAttribute


  /**
   * Set attribute value.
   *
   * @param string $key
   * @param mixed $value
   * @return void
   */
  public function setAttribute( string $key, $value ): void
  {
    $this->attributes[$key] = $value;
  } // setAttribute


  /**
   * Get all attributes.
   *
   * @return array
   */
  public function getAttributes(): array
  {
    return $this->attributes;
  } // getAttributes


  /**
   * Create model instance from array data.
   *
   * @param array $data
   * @return static
   */
  protected function createFromArray( array $data )
  {
    $model = new static( $this->pdo );
    $model->attributes = $data;
    return $model;
  } // createFromArray


  /**
   * Magic getter for attributes.
   *
   * @param string $name
   * @return mixed
   */
  public function __get( string $name )
  {
    return $this->getAttribute( $name );
  } // __get


  /**
   * Magic setter for attributes.
   *
   * @param string $name
   * @param mixed $value
   * @return void
   */
  public function __set( string $name, $value ): void
  {
    $this->setAttribute( $name, $value );
  } // __set


  /**
   * Check if attribute exists.
   *
   * @param string $name
   * @return bool
   */
  public function __isset( string $name ): bool
  {
    return isset( $this->attributes[$name] );
  } // __isset

} // Model 