<?php

namespace Kucbel\Database\Table;

use Kucbel\Database\Query\SelectionIterator;
use Kucbel\Database\Row\MissingRowException;
use Kucbel\Iterators\ArrayIterator;
use Kucbel\Iterators\ChunkIterator;
use Kucbel\Iterators\FilterIterator;
use Kucbel\Iterators\ModifyIterator;
use Nette\Database\Context;
use Nette\Database\SqlLiteral;
use Nette\Database\Table\ActiveRow;
use Nette\Database\Table\Selection;
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
		'cache'		=> false,
		'strict'	=> true,
		'insert'	=> 100,
	];

	/**
	 * @var array
	 */
	protected $defaults = [];

	/**
	 * @var array
	 */
	protected $results = [];

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
	 * @param mixed $key
	 * @param mixed ...$keys
	 * @return ActiveRow
	 * @throws MissingRowException
	 */
	function fetch( $key, ...$keys ) : ActiveRow
	{
		$row = $this->find( $key, ...$keys );

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
	 * @param mixed $key
	 * @param mixed ...$keys
	 * @return ActiveRow | null
	 */
	function find( $key, ...$keys ) : ?ActiveRow
	{
		if( $this->options['cache'] ) {
			$id = $this->primary( $key, ...$keys );

			if( isset( $this->results['row'][ $id ] )) {
				return $this->results['row'][ $id ];
			}
		}

		$query = $this->database->table( $this->name );

		$where = implode(' = ? AND ', (array) $query->getPrimary() );

		$row = $query->where("{$where} = ?", $key, ...$keys )->fetch();

		if( $this->options['cache'] and $row ) {
			$this->results['row'][ $id ] = $row;
		}

		return $row;
	}

	/**
	 * @param array $where
	 * @param array $order
	 * @param bool $limit
	 * @return ActiveRow | null
	 */
	function findOne( array $where = null, array $order = null, bool $limit = false ) : ?ActiveRow
	{
		return $this->select( $where, $order, $limit ? 1 : null )->fetch();
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
		return $this->select( $where, $order, $limit, $offset )->fetchAll();
	}

	/**
	 * @param array $where
	 * @param array $order
	 * @param int $limit
	 * @param int $fetch
	 * @return ActiveRow[]
	 */
	function findLazy( array $where = null, array $order = null, int $limit = null, int $fetch = 100 ) : iterable
	{
		$query = $this->select( $where, $order, $limit );

		return new SelectionIterator( $query, $fetch );
	}

	/**
	 * @param array $order
	 * @return ActiveRow[]
	 */
	function findAll( array $order = null ) : array
	{
		if( isset( $this->results['all'] ) and !$order ) {
			return $this->results['row'];
		}

		$rows = $this->select( null, $order )->fetchAll();

		if( $this->options['cache'] and !$order ) {
			$this->results['all'] = true;
			$this->results['row'] = $rows;
		}

		return $rows;
	}

	/**
	 * @param ActiveRow $row
	 * @param string ...$not
	 * @return ActiveRow | null
	 */
	function refer( ActiveRow $row, string ...$not ) : ?ActiveRow
	{
		$rows = $this->referAll( $row, ...$not );

		foreach( $rows as $row ) {
			return $row;
		}

		return null;
	}

	/**
	 * @param ActiveRow $row
	 * @param string ...$not
	 * @return ActiveRow[]
	 */
	function referAll( ActiveRow $row, string ...$not ) : iterable
	{
		$value = $row->getPrimary();
		$tables = $this->database->getStructure()->getHasManyReference( $this->name );

		if( is_array( $value )) {
			throw new InvalidArgumentException("Row must have scalar primary key.");
		}

		$refers = new ArrayIterator;

		foreach( $tables as $table => $columns ) {
			foreach( $columns as $column ) {
				$refers["{$table}.{$column}"] = [ $table, $column ];
			}
		}

		if( $not or $not = $this->defaults['refer'] ?? null ) {
			$not = array_flip( $not );

			$refers = new FilterIterator( $refers, function( $refer, $index ) use( $not ) {
				return !isset( $not[ $refer[0]] ) and !isset( $not[ $index ]);
			});
		}

		$refers = new ModifyIterator( $refers, function( $refer ) use( $value ) {
			return $this->database->table( $refer[0] )
				->where("{$refer[1]} = ?", $value )
				->limit( 1 )
				->fetch();
		});

		return new FilterIterator( $refers );
	}

	/**
	 * @param Selection $query
	 * @param array $array
	 * @return array
	 */
	protected function list( Selection $query, array $array ) : array
	{
		if( is_string( $value = current( $array ))) {
			$value = function( ActiveRow $row ) use( $value ) {
				return $row->$value;
			};
		} else {
			throw new InvalidArgumentException("Array must have string column name.");
		}

		if( is_string( $index = key( $array ))) {
			$index = function( ActiveRow $row ) use( $index ) {
				return $row->$index;
			};
		} else {
			$index = function( $row, $id, $pos ) {
				return $pos;
			};
		}

		$query = new ModifyIterator( $query, $value, $index );

		return $query->toArray();
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
		$query = $this->select( $where, $order, $limit, $offset );

		return $this->list( $query, $array );
	}

	/**
	 * @param array $array
	 * @param array $order
	 * @return array
	 */
	function listAll( array $array, array $order = null ) : array
	{
		$query = $this->select( null, $order );

		return $this->list( $query, $array );
	}

	/**
	 * @param array $where
	 * @param array $order
	 * @param int $limit
	 * @param int $offset
	 * @return Selection
	 */
	function select( array $where = null, array $order = null, int $limit = null, int $offset = null ) : Selection
	{
		$query = $this->database->table( $this->name );

		if( $where or $where = $this->defaults['where'] ?? null ) {
			foreach( $where as $column => $param ) {
				if( is_int( $column )) {
					$query->where( $param );
				} elseif( is_array( $param ) and array_key_exists( 0, $param ) and substr_count( $column, '?') > 1 ) {
					$query->where( $column, ...$param );
				} else {
					$query->where( $column, $param );
				}
			}
		}

		if( $order or $order = $this->defaults['order'] ?? null ) {
			foreach( $order as $column => $param ) {
				if( is_int( $column )) {
					$query->order( $param );
				} elseif( is_array( $param ) and array_key_exists( 0, $param ) and substr_count( $column, '?') > 1 ) {
					$query->order( $column, ...$param );
				} else {
					$query->order( $column, $param );
				}
			}
		}

		if( $limit or $limit = $this->defaults['limit'] ?? null ) {
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
		return $this->select( $where )->count( $column );
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

		if( $this->options['cache'] ) {
			$id = $row->getSignature();

			$this->results['row'][ $id ] = $row;
		}

		return $row;
	}

	/**
	 * @param array $values
	 * @return int
	 */
	function insertOne( array $values ) : int
	{
		$insert = $this->builder()->buildInsertQuery();
		$insert .= ' ?values';

		$count = $this->database->query( $insert, $values )->getRowCount();

		return $count;
	}

	/**
	 * @param array $values
	 * @param int $batch
	 * @return int
	 */
	function insertMany( array $values, int $batch = null ) : int
	{
		$chunks = new ChunkIterator( $values, $batch ?? $this->options['insert'] );

		$insert = $this->builder()->buildInsertQuery();
		$insert .= ' ?values';

		$count = 0;

		foreach( $chunks as $chunk ) {
			$count += $this->database->query( $insert, $chunk )->getRowCount();
		}

		return $count;
	}

	/**
	 * @return int | null
	 */
	function insertId() : ?int
	{
		$id = (int) $this->database->getInsertId();

		return $id ? $id : null;
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
		return $this->select( $where, $order, $limit )->update( $values );
	}

	/**
	 * @param array $values
	 * @param array $order
	 * @return int
	 */
	function updateAll( array $values, array $order = null ) : int
	{
		return $this->select( null, $order )->update( $values );
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
		return $this->select( $where, $order, $limit )->delete();
	}

	/**
	 * @param array $order
	 * @return int
	 */
	function deleteAll( array $order = null ) : int
	{
		return $this->select( null, $order )->delete();
	}

	/**
	 * @param string $query
	 * @param mixed ...$values
	 * @return int
	 */
	function query( string $query, ...$values ) : int
	{
		return $this->database->query( $query, ...$values )->getRowCount();
	}

	/**
	 * @return $this
	 */
	function clear() : self
	{
		$this->results = null;

		return $this;
	}

	/**
	 * @param mixed ...$keys
	 * @return string
	 */
	protected function primary( ...$keys ) : string
	{
		return implode('|', $keys );
	}

	/**
	 * @return SqlBuilder
	 */
	protected function builder() : SqlBuilder
	{
		return new SqlBuilder( $this->name, $this->database );
	}

	/**
	 * @param string $query
	 * @param mixed ...$values
	 * @return SqlLiteral
	 */
	protected function literal( string $query, ...$values ) : SqlLiteral
	{
		return new SqlLiteral( $query, $values );
	}
}
