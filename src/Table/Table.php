<?php

namespace Kucbel\Database\Table;

use Kucbel\Database\Context;
use Kucbel\Database\Error\MissingRowException;
use Kucbel\Database\Query\Selection;
use Kucbel\Database\Query\SelectionIterator;
use Kucbel\Iterators\AppendIterator;
use Kucbel\Iterators\ChunkIterator;
use Kucbel\Iterators\FilterIterator;
use Kucbel\Iterators\ModifyIterator;
use Nette\Database\Table\ActiveRow;
use Nette\Database\Table\SqlBuilder;
use Nette\InvalidArgumentException;
use Nette\SmartObject;

class Table
{
	use SmartObject;

	/**
	 * @var Context
	 * @inject
	 */
	public $database;

	/**
	 * @var string
	 */
	protected $name;

	/**
	 * @var array
	 */
	protected $options = [
		'select'	=> 100,
		'insert'	=> 100,
		'strict'	=> true,
	];

	/**
	 * @var array
	 */
	protected $defaults = [];

	/**
	 * Table constructor.
	 *
	 * @param string $name
	 * @param array $options
	 * @param array $defaults
	 */
	function __construct( string $name, array $options = null, array $defaults = null )
	{
		$this->name = $name;

		if( $options ) {
			$this->options = $options + $this->options;
		}

		if( $defaults ) {
			$this->defaults = $defaults + $this->defaults;
		}
	}

	/**
	 * @return string
	 */
	function getName() : string
	{
		return $this->name;
	}

	/**
	 * @param mixed $id
	 * @return ActiveRow
	 * @throws MissingRowException
	 */
	function fetch( $id ) : ActiveRow
	{
		$row = $this->find( $id );

		if( !$row ) {
			throw new MissingRowException("Row wasn't found.");
		}

		return $row;
	}

	/**
	 * @param array $where
	 * @param array $order
	 * @param bool $limit
	 * @return ActiveRow
	 * @throws MissingRowException
	 */
	function fetchOne( array $where = null, array $order = null, bool $limit = false ) : ActiveRow
	{
		$row = $this->findOne( $where, $order, $limit );

		if( !$row ) {
			throw new MissingRowException("Row wasn't found.");
		}

		return $row;
	}

	/**
	 * @param mixed $id
	 * @return ActiveRow | null
	 */
	function find( $id ) : ?ActiveRow
	{
		return $this->database->table( $this->name )
			->wherePrimary( $id )
			->fetch();
	}

	/**
	 * @param array $where
	 * @param array $order
	 * @param bool $limit
	 * @return ActiveRow | null
	 */
	function findOne( array $where = null, array $order = null, bool $limit = false ) : ?ActiveRow
	{
		return $this->query( $where, $order, $limit ? 1 : null )->fetch();
	}

	/**
	 * @param array $where
	 * @param array $order
	 * @param int $limit
	 * @param int $offset
	 * @return ActiveRow[]
	 */
	function findMany( array $where = null, array $order = null, int $limit = null, int $offset = null ) : array
	{
		return $this->query( $where, $order, $limit, $offset )->fetchAll();
	}

	/**
	 * @param array $where
	 * @param array $order
	 * @param int $fetch
	 * @return ActiveRow[]
	 */
	function findLazy( array $where = null, array $order = null, int $fetch = null ) : iterable
	{
		$query = $this->query( $where, $order );

		return new SelectionIterator( $query, $fetch ?? $this->options['select'] );
	}

	/**
	 * @param array $order
	 * @return ActiveRow[]
	 */
	function findAll( array $order = null ) : array
	{
		return $this->query( null, $order )->fetchAll();
	}

	/**
	 * @param ActiveRow $row
	 * @param string $table
	 * @param string ...$tables
	 * @return ActiveRow | null
	 */
	function refer( ActiveRow $row, string $table, string ...$tables ) : ?ActiveRow
	{
		$rows = $this->relate( $row, $table, ...$tables );

		foreach( $rows as $row ) {
			return $row;
		}

		return null;
	}

	/**
	 * @param ActiveRow $row
	 * @param string $table
	 * @param string ...$tables
	 * @return ActiveRow[]
	 */
	function relate( ActiveRow $row, string $table, string ...$tables ) : iterable
	{
		$column = $this->options['relate'] ?? "{$this->name}_id";

		if( !is_scalar( $value = $row->getPrimary() ) and !is_object( $value )) {
			throw new InvalidArgumentException('Row must have scalar primary key.');
		} elseif( !is_string( $column )) {
			throw new InvalidArgumentException('Table must have scalar primary key.');
		}

		$queue = new AppendIterator([ $table ], $tables );

		$queue = new ModifyIterator( $queue, function( &$search ) use( $column, $value ) {
			[ $table, $column ] = explode('.', $search, 2 ) + [ 1 => $column ];

			$search = $this->database->table( $table )
				->where("{$column} = ?", $value )
				->limit( 1 )
				->fetch();
		});

		return new FilterIterator( $queue );
	}

	/**
	 * @param Selection $query
	 * @param array $array
	 * @return ModifyIterator
	 */
	protected function modify( Selection $query, array $array ) : iterable
	{
		$index = key( $array );
		$value = current( $array );

		if( !is_string( $value )) {
			throw new InvalidArgumentException("Array must have string column name.");
		}

		if( $assoc = is_string( $index )) {
			$query->select("{$value}, {$index}");
		} else {
			$query->select("{$value}");
		}

		return new ModifyIterator( $query, function( ActiveRow &$row, &$key, $num ) use( $value, $index, $assoc ) {
			$key = $assoc ? $row->$index : $num;
			$row = $row->$value;
		});
	}

	/**
	 * @param array $array
	 * @param array $where
	 * @param array $order
	 * @param int $limit
	 * @param int $offset
	 * @return array
	 */
	function listMany( array $array, array $where = null, array $order = null, int $limit = null, int $offset = null ) : array
	{
		$query = $this->query( $where, $order, $limit, $offset );

		return $this->modify( $query, $array )->toArray();
	}

	/**
	 * @param array $array
	 * @param array $order
	 * @return array
	 */
	function listAll( array $array, array $order = null ) : array
	{
		$query = $this->query( null, $order );

		return $this->modify( $query, $array )->toArray();
	}

	/**
	 * @param array $where
	 * @param array $order
	 * @param int $limit
	 * @param int $offset
	 * @return Selection
	 */
	function query( array $where = null, array $order = null, int $limit = null, int $offset = null ) : Selection
	{
		$query = $this->database->table( $this->name );

		if( $where = $where ?? $this->defaults['where'] ?? null ) {
			foreach( $where as $column => $param ) {
				if( is_int( $column )) {
					$query->where( $param );
				} elseif( is_array( $param ) and substr_count( $column, '?') > 1 ) {
					$query->where( $column, ...$param );
				} else {
					$query->where( $column, $param );
				}
			}
		}

		if( $order = $order ?? $this->defaults['order'] ?? null ) {
			foreach( $order as $column => $param ) {
				if( $param === 'ASC' or $param === 'DESC') {
					$query->order("{$column} {$param}");
				} elseif( is_int( $column )) {
					$query->order( $param );
				} elseif( is_array( $param ) and substr_count( $column, '?') > 1 ) {
					$query->order( $column, ...$param );
				} else {
					$query->order( $column, $param );
				}
			}
		}

		if( $limit = $limit ?? $this->defaults['limit'] ?? null ) {
			$query->limit( $limit, $offset );
		}

		return $query;
	}

	/**
	 * @param array $where
	 * @param string $column
	 * @return int
	 */
	function count( array $where = null, string $column = '*') : int
	{
		return $this->query( $where )->count( $column );
	}

	/**
	 * @param array $values
	 * @return ActiveRow
	 * @throws TableException
	 */
	function insert( array $values ) : ActiveRow
	{
		$row = $this->database->table( $this->name )->insert( $values );

		if( !$row instanceof ActiveRow ) {
			throw new TableException("Table '{$this->name}' didn't insert or return row.");
		}

		return $row;
	}

	/**
	 * @param array $values
	 * @return int
	 */
	function insertOne( array $values ) : int
	{
		$insert = $this->getBuilder()->buildInsertQuery();
		$insert .= ' ?values';

		$count = $this->database->query( $insert, $values )->getRowCount();

		return (int) $count;
	}

	/**
	 * @param array[] $values
	 * @param int $batch
	 * @return int
	 */
	function insertMany( iterable $values, int $batch = null ) : int
	{
		$chunks = new ChunkIterator( $values, $batch ?? $this->options['insert'] );

		$insert = $this->getBuilder()->buildInsertQuery();
		$insert .= ' ?values';

		$count = 0;

		foreach( $chunks as $chunk ) {
			$count += $this->database->query( $insert, $chunk )->getRowCount();
		}

		return $count;
	}

	/**
	 * @param ActiveRow $row
	 * @param array $values
	 * @return bool
	 * @throws TableException
	 */
	function update( ActiveRow $row, array $values ) : bool
	{
		$id = $row->getSignature();
		$ok = $row->update( $values );

		if( $this->options['strict'] and !$ok ) {
			throw new TableException("Table '{$this->name}' didn't update row #{$id}.");
		}

		return $ok;
	}

	/**
	 * @param array $values
	 * @param array $where
	 * @param array $order
	 * @param int $limit
	 * @return int
	 */
	function updateMany( array $values, array $where = null, array $order = null, int $limit = null ) : int
	{
		return $this->query( $where, $order, $limit )->update( $values );
	}

	/**
	 * @param array $values
	 * @param array $order
	 * @return int
	 */
	function updateAll( array $values, array $order = null ) : int
	{
		return $this->query( null, $order )->update( $values );
	}

	/**
	 * @param ActiveRow $row
	 * @return bool
	 * @throws TableException
	 */
	function delete( ActiveRow $row ) : bool
	{
		$id = $row->getSignature();
		$ok = $row->delete();

		if( $this->options['strict'] and !$ok ) {
			throw new TableException("Table '{$this->name}' didn't delete row #{$id}.");
		}

		return $ok;
	}

	/**
	 * @param array $where
	 * @param array $order
	 * @param int $limit
	 * @return int
	 */
	function deleteMany( array $where = null, array $order = null, int $limit = null ) : int
	{
		return $this->query( $where, $order, $limit )->delete();
	}

	/**
	 * @param array $order
	 * @return int
	 */
	function deleteAll( array $order = null ) : int
	{
		return $this->query( null, $order )->delete();
	}

	/**
	 * @return int
	 */
	function getInsertId() : int
	{
		$id = (int) $this->database->getInsertId();

		if( !$id ) {
			throw new TableException("Table '{$this->name}' doesn't have auto increment.");
		}

		return $id;
	}

	/**
	 * @return SqlBuilder
	 */
	protected function getBuilder() : SqlBuilder
	{
		return new SqlBuilder( $this->name, $this->database );
	}
}
