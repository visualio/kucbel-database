<?php

namespace Kucbel\Database\Table;

use Kucbel\Database\Error\MissingRowException;
use Kucbel\Database\Explorer;
use Kucbel\Database\Literal;
use Kucbel\Database\Query\Selection;
use Kucbel\Database\Query\SelectionIterator;
use Kucbel\Database\Trigger;
use Kucbel\Iterators\ChunkIterator;
use Kucbel\Iterators\FilterIterator;
use Kucbel\Iterators\ModifyIterator;
use Nette\Database\Table\ActiveRow;
use Nette\InvalidArgumentException;
use Nette\SmartObject;

class Table
{
	use SmartObject, Trigger;

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
	 * @param array|null $options
	 * @param array|null $defaults
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
	 * @param string | int | array $id
	 * @return ActiveRow
	 * @throws MissingRowException
	 */
	function fetch( string | int | array $id ) : ActiveRow
	{
		$row = $this->find( $id );

		if( !$row ) {
			$key = $this->getPrimary( $id );

			throw new MissingRowException("Row {$key} wasn't found.");
		}

		return $row;
	}

	/**
	 * @param array | null $where
	 * @param array | null $order
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
	 * @return ActiveRow[]
	 * @throws MissingRowException
	 */
	function fetchEnum( string | int ...$ids ) : array
	{
		$rows = $this->findEnum( ...$ids );

		foreach( $ids as $id ) {
			if( !isset( $rows[ $id ] )) {
				$key = $this->getPrimary( $id );

				throw new MissingRowException("Row {$key} wasn't found.");
			}
		}

		return $rows;
	}

	/**
	 * @param mixed $id
	 * @return ActiveRow | null
	 */
	function find( string | int | array | null $id ) : ActiveRow | null
	{
		if( $id === '' or $id === [] or $id === null ) {
			return null;
		}

		return $this->explorer->table( $this->table )
			->wherePrimary( $id )
			->fetch();
	}

	/**
	 * @param array | null $where
	 * @param array | null $order
	 * @param bool $limit
	 * @return ActiveRow | null
	 */
	function findOne( array $where = null, array $order = null, bool $limit = false ) : ActiveRow | null
	{
		return $this->query( $where, $order, $limit ? [ 1 ] : null )->fetch();
	}

	/**
	 * @param array | null $where
	 * @param array | null $order
	 * @param array | null $limit
	 * @return ActiveRow[]
	 */
	function findMany( array $where = null, array $order = null, array $limit = null ) : array
	{
		return $this->query( $where, $order, $limit )->fetchAll();
	}

	/**
	 * @param array | null $where
	 * @param array | null $order
	 * @param int | null $limit
	 * @return ActiveRow[]
	 */
	function findLazy( array $where = null, array $order = null, int $limit = null ) : iterable
	{
		$query = $this->query( $where, $order );

		return new SelectionIterator( $query, $limit ?? $this->options['select'] );
	}

	/**
	 * @param array | null $order
	 * @return ActiveRow[]
	 */
	function findAll( array $order = null ) : array
	{
		return $this->query( null, $order )->fetchAll();
	}

	/**
	 * @param string | int | null ...$ids
	 * @return ActiveRow[]
	 */
	function findEnum( string | int | null ...$ids ) : array
	{
		$idx = [];

		foreach( $ids as $id ) {
			if( $id !== null ) {
				$idx[ $id ] = $id;
			}
		}

		if( !$idx ) {
			return [];
		}

		return $this->explorer->table( $this->table )
			->wherePrimary( array_values( $idx ))
			->fetchAll();
	}

	/**
	 * @param array | null $where
	 * @param array | null $order
	 * @param array | null $limit
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
	 * @param array | null $where
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
	 * @return FilterIterator
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
	 * @param array | null $where
	 * @param array | null $order
	 * @param array | null $limit
	 * @return array
	 */
	function listMany( array $array, array $where = null, array $order = null, array $limit = null ) : array
	{
		return $this->select( $array, $where, $order, $limit );
	}

	/**
	 * @param array $array
	 * @param array | null $order
	 * @return array
	 */
	function listAll( array $array, array $order = null ) : array
	{
		return $this->select( $array, null, $order );
	}

	/**
	 * @param array $array
	 * @param array | null $where
	 * @param array | null $order
	 * @param array | null $limit
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
	 * @param array $insert
	 * @return ActiveRow
	 * @throws TableException
	 */
	function insert( array $insert ) : ActiveRow
	{
		$this->dispatch('pre-insert', $insert );

		$row = $this->explorer->table( $this->table )->insert( $insert );

		if( !$row instanceof ActiveRow ) {
			throw new TableException("Table \"{$this->table}\" doesn't have primary key.");
		}

		$this->dispatch('post-insert', $row );

		return $row;
	}

	/**
	 * @param array $insert
	 * @return int
	 */
	function insertOne( array $insert ) : int
	{
		if( !$insert ) {
			return 0;
		}

		$this->dispatch('pre-insert-one', $insert );

		if( !$insert ) {
			return 0;
		}

		$count = (int) $this->explorer->query('INSERT INTO ?name ?values', $this->table, $insert )->getRowCount();

		$this->dispatch('post-insert-one', $count );

		return $count;
	}

	/**
	 * @param array $insert
	 * @param array $update
	 * @return int
	 */
	function insertKey( array $insert, array $update ) : int
	{
		if( !$insert or !$update ) {
			return 0;
		}

		$this->dispatch('pre-insert-key', $insert, $update );

		if( !$insert or !$update ) {
			return 0;
		}

		$count = (int) $this->explorer->query('INSERT INTO ?name ?values ON DUPLICATE KEY UPDATE ?set', $this->table, $insert, $update )->getRowCount();

		$this->dispatch('post-insert-key', $count );

		return $count;
	}

	/**
	 * @param array[] $insert
	 * @param int | null $batch
	 * @return int
	 */
	function insertMany( iterable $insert, int $batch = null ) : int
	{
		if( !$insert ) {
			return 0;
		}

		$this->dispatch('pre-insert-many', $insert );

		if( !$insert ) {
			return 0;
		}

		$chunks = new ChunkIterator( $insert, $batch ?? $this->options['insert'] );
		$count = 0;

		foreach( $chunks as $chunk ) {
			$count += (int) $this->explorer->query('INSERT INTO ?name ?values', $this->table, $chunk )->getRowCount();
		}

		$this->dispatch('pre-insert-many', $count );

		return $count;
	}

	/**
	 * @param ActiveRow $row
	 * @param array $update
	 * @return bool
	 * @throws TableException
	 */
	function update( ActiveRow $row, array $update ) : bool
	{
		if( !$update ) {
			return false;
		}

		$this->dispatch('pre-update', $row, $update );

		if( !$update ) {
			return false;
		}

		$index = $row->getPrimary();
		$count = $row->update( $update );

		if( $this->options['strict'] and !$count ) {
			$key = $this->getPrimary( $index );

			throw new TableException("Row {$key} wasn't updated.");
		}

		$this->dispatch('post-update', $row, $count );

		return $count;
	}

	/**
	 * @param array $update
	 * @param array | null $where
	 * @param array | null $order
	 * @param int | null $limit
	 * @return int
	 */
	function updateMany( array $update, array $where = null, array $order = null, int $limit = null ) : int
	{
		if( !$update ) {
			return 0;
		}

		$this->dispatch('pre-update-many', $update );

		if( !$update ) {
			return 0;
		}

		$count = $this->query( $where, $order, [ $limit ])->update( $update );

		$this->dispatch('post-update-many', $count );

		return $count;
	}

	/**
	 * @param array $update
	 * @param array | null $order
	 * @return int
	 */
	function updateAll( array $update, array $order = null ) : int
	{
		if( !$update ) {
			return 0;
		}

		$this->dispatch('pre-update-all', $update );

		if( !$update ) {
			return 0;
		}

		$count = $this->query( null, $order )->update( $update );

		$this->dispatch('post-update-all', $count );

		return $count;
	}

	/**
	 * @param ActiveRow $row
	 * @return bool
	 * @throws TableException
	 */
	function delete( ActiveRow $row ) : bool
	{
		$this->dispatch('pre-delete', $row );

		$index = $row->getPrimary();
		$count = $row->delete();

		if( $this->options['strict'] and !$count ) {
			$key = $this->getPrimary( $index );

			throw new TableException("Row {$key} wasn't deleted.");
		}

		$this->dispatch('post-delete', $index, $count );

		return $count;
	}

	/**
	 * @param array | null $where
	 * @param array | null $order
	 * @param int | null $limit
	 * @return int
	 */
	function deleteMany( array $where = null, array $order = null, int $limit = null ) : int
	{
		$this->dispatch('pre-delete-many');

		$count = $this->query( $where, $order, [ $limit ])->delete();

		$this->dispatch('post-delete-many', $count );

		return $count;
	}

	/**
	 * @return int
	 */
	function deleteAll() : int
	{
		$this->dispatch('pre-delete-all');

		$count = $this->query()->delete();

		$this->dispatch('post-delete-all', $count );

		return $count;
	}

	/**
	 * @param array $update
	 * @param array | null $where
	 * @param array | null $order
	 * @param int | null $limit
	 * @return int
	 */
	function adjust( array $update, array $where = null, array $order = null, int $limit = null ) : int
	{
		foreach( $update as $index => $value ) {
			if( !is_int( $value ) and !is_float( $value )) {
				continue;
			}

			if( $value > 0 ) {
				$equal = '+';
			} elseif( $value < 0 ) {
				$equal = '-';
				$value = - $value;
			} else {
				unset( $update[ $index ] );

				continue;
			}

			$update[ $index ] = new Literal("?name {$equal} ?", $index, $value );
		}

		return $this->updateMany( $update, $where, $order, $limit );
	}

	/**
	 * @param bool $read
	 */
	function lock( bool $read = false ) : void
	{
		$mode = $read ? 'READ' : 'WRITE';

		$this->explorer->query("LOCK TABLES ?name {$mode}", $this->table );
	}

	/**
	 * @return void
	 */
	function unlock() : void
	{
		$this->explorer->query('UNLOCK TABLES');
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

	/**
	 * @param string | int | array | null $value
	 * @return string
	 */
	protected function getPrimary( string | int | array | null $value ) : string
	{
		if( is_string( $value )) {
			return "\"{$value}\"";
		} elseif( is_int( $value )) {
			return "#{$value}";
		} elseif( is_array( $value )) {
			$array = [];

			foreach( $value as $index => $piece ) {
				$array[] = is_string( $index ) ? "{$index} : {$piece}" : $piece;
			}

			if( $array ) {
				$array = implode(', ', $array );

				return "[ {$array} ]";
			} else {
				return '[]';
			}
		} else {
			return 'null';
		}
	}
}
