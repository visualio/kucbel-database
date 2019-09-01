<?php

namespace Kucbel\Database\Table;

use Kucbel\Database\Row\MissingRowException;
use Nette\Database\Context;
use Nette\Database\ResultSet;
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
		'strict'	=> true,
		'insert'	=> 100,
	];

	/**
	 * Table constructor.
	 *
	 * @param string $name
	 * @param array $options
	 */
	function __construct( string $name, array $options = null )
	{
		$this->name = $name;

		if( $options ) {
			$this->options = $options + $this->options;
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
		$query = $this->database->table( $this->name );

		$where = implode(' = ? AND ', (array) $query->getPrimary() );

		return $query->where("{$where} = ?", $key, ...$keys )->fetch();
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
	function findMany( array $where = null, array $order = null, int $limit = null, int $offset = null ) : iterable
	{
		return $this->select( $where, $order, $limit, $offset )->fetchAll();
	}

	/**
	 * @param array $order
	 * @return ActiveRow[]
	 */
	function findAll( array $order = null ) : iterable
	{
		return $this->select( null, $order )->fetchAll();
	}

	/**
	 * @param ActiveRow $row
	 * @param string ...$skip
	 * @return ActiveRow[]
	 */
	function findRef( ActiveRow $row, string ...$skip ) : iterable
	{
		$key = $row->getPrimary();

		if( !is_scalar( $key ) and !is_object( $key )) {
			throw new InvalidArgumentException("Row must have scalar primary key.");
		}

		if( $skip ) {
			$skip = array_flip( $skip );
		}

		$tables = $this->database->getStructure()->getHasManyReference( $this->name );
		$rows = [];

		foreach( $tables as $table => $columns ) {
			if( array_key_exists( $table, $skip )) {
				continue;
			}

			$param =
			$where = null;

			foreach( $columns as $column ) {
				if( array_key_exists("{$table}.{$column}", $skip )) {
					continue;
				}

				$where[] = "{$column} = ?";
				$param[] = $key;
			}

			if( !$where ) {
				continue;
			}

			$row = $this->database->table( $table )
				->where( implode(' OR ', $where ), ...$param )
				->limit( 1 )
				->fetch();

			if( $row ) {
				$rows[] = $row;
			}
		}

		return $rows;
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

		if( $where ) {
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

		if( $order ) {
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

		if( $limit ) {
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

		if( !$count and $this->options['strict'] ) {
			throw new TableException("Table '{$this->name}' didn't insert row.");
		}

		return $count;
	}

	/**
	 * @param array $values
	 * @return int
	 */
	function insertMany( array $values ) : int
	{
		$queue = count( $values );

		if( $queue > $this->options['insert'] ) {
			$chunks = array_chunk( $values, $this->options['insert'] );
		} elseif( $queue ) {
			$chunks[] = $values;
		} else {
			$chunks = [];
		}

		$insert = $this->builder()->buildInsertQuery();
		$insert .= ' ?values';

		$count = 0;

		foreach( $chunks as $chunk ) {
			$count += $this->database->query( $insert, $chunk )->getRowCount();
		}

		if( $count !== $queue and $this->options['strict'] ) {
			$queue -= $count;

			throw new TableException("Table '{$this->name}' didn't insert {$queue} rows.");
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

		if( !$ok and $this->options['strict'] ) {
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
	 * @param ActiveRow $row
	 * @return bool
	 * @throws TableException
	 */
	function delete( ActiveRow $row ) : bool
	{
		$id = $row->getSignature();
		$ok = $row->delete();

		if( !$ok and $this->options['strict'] ) {
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
	 * @param string $command
	 * @param mixed ...$arguments
	 * @return ResultSet
	 */
	function query( string $command, ...$arguments ) : ResultSet
	{
		return $this->database->query( $command, ...$arguments );
	}

	/**
	 * @param string $name
	 * @return string
	 */
	function escape( string $name ) : string
	{
		$driver = $this->database->getConnection()->getSupplementalDriver();

		return $driver->delimite( $name );
	}

	/**
	 * @return SqlBuilder
	 */
	protected function builder() : SqlBuilder
	{
		return new SqlBuilder( $this->name, $this->database );
	}
}
