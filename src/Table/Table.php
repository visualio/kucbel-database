<?php

namespace Kucbel\Database\Table;

use Kucbel\Database\Context;
use Kucbel\Database\Error\MissingRowException;
use Kucbel\Database\Query\Selection;
use Kucbel\Database\Query\SelectionIterator;
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
		if( is_string( $id ) or is_int( $id )) {
			return $this->database->table( $this->name )
				->wherePrimary( $id )
				->fetch();
		} else {
			return null;
		}
	}

	/**
	 * @param mixed ...$ids
	 * @return array
	 */
	function fetchEnum( ...$ids ) : array
	{
		$rows = $this->findEnum( ...$ids );

		foreach( $ids as $id ) {
			if( !is_string( $id ) and !is_int( $id )) {
				throw new MissingRowException("Key isn't valid.");
			} elseif( !isset( $rows[ $id ] )) {
				throw new MissingRowException("Row wasn't found.");
			}
		}

		return $rows;
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
	 * @param mixed ...$ids
	 * @return array
	 */
	function findEnum( ...$ids ) : array
	{
		$idx = [];

		foreach( $ids as $id ) {
			if( is_string( $id ) or is_int( $id )) {
				$idx[ $id ] = $id;
			}
		}

		if( $idx ) {
			return $this->database->table( $this->name )
				->wherePrimary( $idx )
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
	function linkOne( ActiveRow $row, string $name, string ...$names ) : ?ActiveRow
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
	function linkAll( ActiveRow $row, string $name, string ...$names ) : array
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
		if( is_array( $param = $row->getPrimary() )) {
			throw new InvalidArgumentException('Row must have scalar primary key.');
		}

		$schema = $this->database->getConventions();

		foreach( $names as $i => $name ) {
			if( strpos( $name, '.')) {
				$names[ $i ] = explode('.', $name, 2 );
			} elseif( $join = $schema->getHasManyReference( $this->name, $name )){
				$names[ $i ] = $join;
			} else {
				throw new InvalidArgumentException("Table doesn't have a reference to '{$name}'.");
			}
		}

		$queue = new ModifyIterator( $names, function( &$value ) use( $param ) {
			$value = $this->database->table( $value[0] )
				->where("{$value[1]} = ?", $param )
				->order( $value[1] )
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
	 * @return int
	 */
	function getInsertId() : int
	{
		$id = $this->database->getInsertId();

		if( !$id ) {
			throw new TableException("Table '{$this->name}' doesn't have auto increment.");
		}

		return (int) $id;
	}

	/**
	 * @return SqlBuilder
	 */
	protected function getBuilder() : SqlBuilder
	{
		return new SqlBuilder( $this->name, $this->database );
	}
}
