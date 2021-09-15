<?php

namespace Kucbel\Database\Table;

use Kucbel\Database\Error\MissingRowException;
use Kucbel\Database\Explorer;
use Kucbel\Database\Literal;
use Kucbel\Database\Query\Selection;
use Kucbel\Database\Query\SelectionIterator;
use Kucbel\Iterators\ChunkIterator;
use Kucbel\Iterators\FilterIterator;
use Kucbel\Iterators\ModifyIterator;
use Nette\Database\Table\ActiveRow;
use Nette\InvalidArgumentException;
use Nette\SmartObject;

class Table
{
	use SmartObject;

	/**
	 * @var Explorer
	 * @inject
	 */
	public $explorer;

	/**
	 * @var string
	 */
	protected $table;

	/**
	 * @var string | null
	 * @deprecated
	 */
	protected $quote;

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
	 * @param string $table
	 * @param array $options
	 * @param array $defaults
	 */
	function __construct( string $table, array $options = null, array $defaults = null )
	{
		$this->table = $table;

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
	function getTable() : string
	{
		return $this->table;
	}

	/**
	 * @return string
	 * @deprecated
	 */
	function getName() : string
	{
		return $this->table;
	}

	/**
	 * @return string
	 * @deprecated
	 */
	function getQuotedName() : string
	{
		return $this->quote ?? $this->quote = $this->explorer->getConnection()->getDriver()->delimite( $this->table );
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
	 * @param string | int ...$ids
	 * @return array
	 */
	function fetchEnum( ...$ids ) : array
	{
		$rows = $this->findEnum( ...$ids );

		foreach( $ids as $id ) {
			$id = (string) $id;

			if( !isset( $rows[ $id ] )) {
				throw new MissingRowException("Row wasn't found.");
			}
		}

		return $rows;
	}

	/**
	 * @param mixed $id
	 * @return ActiveRow | null
	 */
	function find( $id ) : ?ActiveRow
	{
		if( $id !== null ) {
			return $this->explorer->table( $this->table )
				->wherePrimary( $id )
				->fetch();
		} else {
			return null;
		}
	}

	/**
	 * @param array $where
	 * @param array $order
	 * @param bool $limit
	 * @return ActiveRow | null
	 */
	function findOne( array $where = null, array $order = null, bool $limit = false ) : ?ActiveRow
	{
		return $this->query( $where, $order, $limit ? [ 1 ] : null )->fetch();
	}

	/**
	 * @param array $where
	 * @param array $order
	 * @param array $limit
	 * @return ActiveRow[]
	 */
	function findMany( array $where = null, array $order = null, array $limit = null ) : array
	{
		return $this->query( $where, $order, $limit )->fetchAll();
	}

	/**
	 * @param array $where
	 * @param array $order
	 * @param int $limit
	 * @return ActiveRow[]
	 */
	function findLazy( array $where = null, array $order = null, int $limit = null ) : iterable
	{
		$query = $this->query( $where, $order );

		return new SelectionIterator( $query, $limit ?? $this->options['select'] );
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
	 * @param string | int ...$ids
	 * @return array
	 */
	function findEnum( ...$ids ) : array
	{
		$idx = [];

		foreach( $ids as $id ) {
			if( $id !== null ) {
				$idx[ (string) $id ] = $id;
			}
		}

		if( $idx ) {
			return $this->explorer->table( $this->table )
				->wherePrimary( array_values( $idx ))
				->fetchAll();
		} else {
			return [];
		}
	}

	/**
	 * @param array $where
	 * @param array $order
	 * @param array $limit
	 * @return Selection
	 */
	function query( array $where = null, array $order = null, array $limit = null ) : Selection
	{
		$query = $this->explorer->table( $this->table );

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
			$query->limit( current( $limit ), key( $limit ));
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
	 * @param ActiveRow $row
	 * @param string $name
	 * @param string ...$names
	 * @return ActiveRow | null
	 */
	function existOne( ActiveRow $row, string $name, string ...$names ) : ?ActiveRow
	{
		$rows = $this->lookup( $row, $name, ...$names );

		foreach( $rows as $row ) {
			return $row;
		}

		return null;
	}

	/**
	 * @param ActiveRow $row
	 * @param string $name
	 * @param string ...$names
	 * @return ActiveRow[]
	 */
	function existMany( ActiveRow $row, string $name, string ...$names ) : array
	{
		return $this->lookup( $row, $name, ...$names )->toArray();
	}

	/**
	 * @param ActiveRow $row
	 * @param string ...$names
	 * @return FilterIterator & ActiveRow[]
	 */
	protected function lookup( ActiveRow $row, string ...$names ) : iterable
	{
		if( is_array( $value = $row->getPrimary() )) {
			throw new InvalidArgumentException('Row must have scalar primary key.');
		}

		$schema = $this->explorer->getConventions();

		foreach( $names as $i => $name ) {
			if( strpos( $name, '.')) {
				$names[ $i ] = explode('.', $name, 2 );
			} elseif( $join = $schema->getHasManyReference( $this->table, $name )){
				$names[ $i ] = $join;
			} else {
				throw new InvalidArgumentException("Table \"{$this->table}\" doesn't have a reference to \"{$name}\".");
			}
		}

		$queue = new ModifyIterator( $names, function( &$query ) use( $value ) {
			$query = $this->explorer->table( $query[0] )
				->where("{$query[1]} = ?", $value )
				->limit( 1 )
				->fetch();
		});

		return new FilterIterator( $queue );
	}

	/**
	 * @param array $array
	 * @param array $where
	 * @param array $order
	 * @param array $limit
	 * @return array
	 */
	function listMany( array $array, array $where = null, array $order = null, array $limit = null ) : array
	{
		return $this->select( $array, $where, $order, $limit );
	}

	/**
	 * @param array $array
	 * @param array $order
	 * @return array
	 */
	function listAll( array $array, array $order = null ) : array
	{
		return $this->select( $array, null, $order );
	}

	/**
	 * @param array $array
	 * @param array $where
	 * @param array $order
	 * @param array $limit
	 * @return array
	 */
	protected function select( array $array, array $where = null, array $order = null, array $limit = null ) : array
	{
		if( !is_string( $value = current( $array ))) {
			throw new InvalidArgumentException("Array must contain string column name.");
		}

		if( !is_string( $index = key( $array ))) {
			$index = null;
		}

		$query = $this->query( $where, $order, $limit );

		if( $index !== null ) {
			$query->select("{$value}, {$index}");
		} else {
			$query->select( $value );
		}

		if( $trim = strrpos( $value, '.')) {
			$value = substr( $value, $trim + 1 );
		}

		if( $index !== null and $trim = strrpos( $index, '.')) {
			$index = substr( $index, $trim + 1 );
		}

		return $query->fetchPairs( $index, $value );
	}

	/**
	 * @param array $values
	 * @return ActiveRow
	 * @throws TableException
	 */
	function insert( array $values ) : ActiveRow
	{
		$row = $this->explorer->table( $this->table )->insert( $values );

		if( !$row instanceof ActiveRow ) {
			throw new TableException("Table \"{$this->table}\" didn't return row.");
		}

		return $row;
	}

	/**
	 * @param array $values
	 * @return int
	 */
	function insertOne( array $values ) : int
	{
		$count = $this->explorer->query('INSERT INTO ?name ?values', $this->table, $values )->getRowCount();

		return (int) $count;
	}

	/**
	 * @param array $values1
	 * @param array $values2
	 * @return int
	 */
	function insertKey( array $values1, array $values2 ) : int
	{
		$count = $this->explorer->query('INSERT INTO ?name ?values ON DUPLICATE KEY UPDATE ?set', $this->table, $values1, $values2 )->getRowCount();

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
		$count = 0;

		foreach( $chunks as $chunk ) {
			$count += $this->explorer->query('INSERT INTO ?name ?values', $this->table, $chunk )->getRowCount();
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
			throw new TableException("Table '{$this->table}' didn't update row #{$id}.");
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
		return $this->query( $where, $order, [ $limit ])->update( $values );
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
			throw new TableException("Table '{$this->table}' didn't delete row #{$id}.");
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
		return $this->query( $where, $order, [ $limit ])->delete();
	}

	/**
	 * @return int
	 */
	function deleteAll() : int
	{
		return $this->query()->delete();
	}

	/**
	 * @param array $values
	 * @param array $where
	 * @param array $order
	 * @param int $limit
	 * @return int
	 */
	function adjust( array $values, array $where = null, array $order = null, int $limit = null ) : int
	{
		foreach( $values as $field => $value ) {
			if( is_int( $value ) or is_float( $value )) {
				if( $value > 0 ) {
					$equal = '+';
				} elseif( $value < 0 ) {
					$equal = '-';
					$value = - $value;
				} else {
					throw new InvalidArgumentException("Value can't be zero.");
				}

				$values[ $field ] = new Literal("?name {$equal} ?", $field, $value );
			}
		}

		return $this->updateMany( $values, $where, $order, $limit );
	}

	/**
	 * @return void
	 */
	function reset() : void
	{
		$this->explorer->query('TRUNCATE ?name', $this->table );
	}

	/**
	 * @return int
	 */
	function getInsertId() : int
	{
		$id = $this->explorer->getInsertId();

		if( !$id ) {
			throw new TableException("Table \"{$this->table}\" doesn't have auto increment.");
		}

		return (int) $id;
	}
}
