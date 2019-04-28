<?php

namespace Kucbel\Database\Table;

use Kucbel\Database\Context;
use Kucbel\Database\Query\Selection;
use Kucbel\Database\Row\ActiveRow;
use Kucbel\Database\Row\MissingRowException;
use Nette\Database\Table\IRow;
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
	 * @var SqlBuilder | null
	 */
	private $builder;

	/**
	 * @var array
	 */
	private $options = [
		'strict'	=> true,
		'insert'	=> 100,
	];

	/**
	 * @var string
	 */
	private $name;

	/**
	 * Table constructor.
	 *
	 * @param string $name
	 */
	function __construct( string $name )
	{
		$this->name = $name;
	}

	/**
	 * @param string $name
	 * @param mixed $value
	 * @return $this
	 */
	function setOption( string $name, $value )
	{
		if( gettype( $value ) !== gettype( $this->options[ $name ] ?? null )) {
			throw new InvalidArgumentException("Invalid data type.");
		}

		$this->options[ $name ] = $value;

		return $this;
	}

	/**
	 * @return string
	 */
	function getName() : string
	{
		return $this->name;
	}

	/**
	 * @return SqlBuilder
	 */
	function getBuilder() : SqlBuilder
	{
		return $this->builder ?? $this->builder = new SqlBuilder( $this->name, $this->database );
	}

	/**
	 * @param array $values
	 * @return ActiveRow
	 * @throws TableException
	 */
	function insert( array $values )
	{
		/** @var ActiveRow|IRow|int $row */
		$row = $this->database->table( $this->name )->insert( $values );

		if( !$row instanceof IRow ) {
			throw new TableException("Table '{$this->name}' didn't return inserted row.");
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

		return $this->database->query( $insert, $values )->getRowCount();
	}

	/**
	 * @param array $values
	 * @return int
	 */
	function insertMany( array $values ) : int
	{
		if( count( $values ) > $this->options['insert'] ) {
			$chunks = array_chunk( $values, $this->options['insert'] );
		} elseif( $values ) {
			$chunks[] = $values;
		} else {
			$chunks = [];
		}

		$insert = $this->getBuilder()->buildInsertQuery();
		$insert .= ' ?values';
		
		$count = 0;

		foreach( $chunks as $chunk ) {
			$count += $this->database->query( $insert, $chunk )->getRowCount();
		}

		return $count;
	}

	/**
	 * @param array $values
	 * @param ActiveRow $row
	 * @return bool
	 * @throws TableException
	 */
	function update( array $values, ActiveRow $row ) : bool
	{
		$id = $row->getSignature();
		$ok = $row->update( $values );

		if( !$ok and $this->options['strict'] ) {
			throw new TableException("Table '{$this->name}' didn't update row #{$id}, just so you know.");
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
	 * @param ActiveRow $row
	 * @return bool
	 * @throws TableException
	 */
	function delete( ActiveRow $row ) : bool
	{
		$id = $row->getSignature();
		$ok = $row->delete();

		if( !$ok and $this->options['strict'] ) {
			throw new TableException("Table '{$this->name}' didn't delete row #{$id}, just so you know.");
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
	 * @param mixed $key
	 * @param mixed ...$keys
	 * @return ActiveRow
	 * @throws MissingRowException
	 */
	function fetch( $key, ...$keys )
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
	function fetchOne( array $where = null, array $order = null, bool $limit = false )
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
	function find( $key, ...$keys )
	{
		$query = $this->database->table( $this->name );

		$where = implode(' = ? AND ', (array) $query->getPrimary() );

		/** @var ActiveRow|false $row */
		$row = $query->where("{$where} = ?", $key, ...$keys )->fetch();

		return $row ? $row : null;
	}

	/**
	 * @param array $where
	 * @param array $order
	 * @param bool $limit
	 * @return ActiveRow | null
	 */
	function findOne( array $where = null, array $order = null, bool $limit = false )
	{
		/** @var ActiveRow|false $row */
		$row = $this->query( $where, $order, $limit ? 1 : null )->fetch();

		return $row ? $row : null;
	}

	/**
	 * @param array $where
	 * @param array $order
	 * @param int $limit
	 * @param int $offset
	 * @return ActiveRow[]
	 */
	function findMany( array $where = null, array $order = null, int $limit = null, int $offset = null )
	{
		return $this->query( $where, $order, $limit, $offset )->fetchAll();
	}

	/**
	 * @param array $order
	 * @return ActiveRow[]
	 */
	function findAll( array $order = null )
	{
		return $this->query( null, $order )->fetchAll();
	}

	/**
	 * @param array $where
	 * @param array $order
	 * @param int $limit
	 * @param int $offset
	 * @return Selection
	 */
	function query( array $where = null, array $order = null, int $limit = null, int $offset = null )
	{
		$query = $this->database->table( $this->name );

		if( $where ) {
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

		if( $order ) {
			foreach( $order as $column => $param ) {
				if( is_int( $column )) {
					$query->order( $param );
				} elseif( is_array( $param ) and substr_count( $column, '?') > 1 ) {
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
		return $this->query( $where )->count( $column );
	}

	/**
	 * @param ActiveRow $row
	 * @param string ...$skip
	 * @return bool
	 */
	function refer( ActiveRow $row, string ...$skip ) : bool
	{
		$key = $row->getPrimary();

		if( is_array( $key )) {
			throw new TableException("Unable to reference check for compound key.");
		}

		$tables = $this->database->getStructure()->getHasManyReference( $this->name );

		foreach( $tables as $name => $columns ) {
			if( in_array( $name, $skip, true )) {
				continue;
			}

			$param = array_fill( 0, count( $columns ), $key );
			$where = implode('= ? OR ', $columns );

			$count = $this->database->table( $name )
				->where("{$where} = ?", ...$param )
				->count('*');

			if( $count ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * @return void
	 */
	function lock() : void
	{
		$driver = $this->database->getConnection()->getSupplementalDriver();
		$name = $driver->delimite( $this->name );

		$this->database->query("LOCK TABLES {$name} WRITE");
	}

	/**
	 * @return void
	 */
	function unlock() : void
	{
		$this->database->query('UNLOCK TABLES');
	}
}