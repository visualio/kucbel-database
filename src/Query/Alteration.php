<?php

namespace Kucbel\Database\Query;

use JsonSerializable;
use Kucbel\Database\Repository;
use Kucbel\Database\Row\ActiveRow;
use Kucbel\Iterators\ModifyIterator;
use Nette;
use Nette\InvalidArgumentException;

trait Alteration
{
	/**
	 * @var Repository
	 */
	protected $repository;

	/**
	 * @var string
	 */
	protected $instance;

	/**
	 * @param array $row
	 * @return ActiveRow
	 */
	protected function createRow( array $row ) : Nette\Database\Table\ActiveRow
	{
		return new $this->instance( $row, $this );
	}

	/**
	 * @param string $table
	 * @return Selection
	 */
	function createSelectionInstance( string $table = null ) : Nette\Database\Table\Selection
	{
		/** @var Selection $this */
		return new Selection( $this->repository, $this->context, $this->conventions, $this->cache ? $this->cache->getStorage() : null, $table ?? $this->name );
	}

	/**
	 * @param string $table
	 * @param string $column
	 * @return SelectionGroup
	 */
	protected function createGroupedSelectionInstance( $table, $column ) : Nette\Database\Table\GroupedSelection
	{
		/** @var Selection $this */
		return new SelectionGroup( $this->repository, $this->context, $this->conventions, $this, $this->cache ? $this->cache->getStorage() : null, $table, $column );
	}

	/**
	 * @param mixed $value
	 * @return $this
	 */
	function wherePrimary( $value )
	{
		$column = $this->getPrimary();

		if( is_array( $column )) {
			if( !is_array( $value )) {
				throw new InvalidArgumentException("Value must be an array.");
			}

			if( is_array( current( $value ))) {
				$chain = [];

				foreach( $value as $each ) {
					$check = [];

					foreach( $column as $index => $name ) {
						$check[] = $each[ $index ] ?? $each[ $name ] ?? null;
					}

					$chain[] = $check;
				}

				foreach( $column as $index => $name ) {
					$column[ $index ] = "{$this->name}.{$name}";
				}

				$column = implode(', ', $column );

				$this->where("($column) IN ?", $chain );
			} else {
				foreach( $column as $index => $name ) {
					$this->where("{$this->name}.{$name}", $value[ $index ] ?? $value[ $name ] ?? null );
				}
			}
		} elseif( is_array( $value )) {
			if( is_string( $index = key( $value ))) {
				foreach( $value as $name => $each ) {
					$this->where("{$this->name}.{$name}", $each );
				}
			} elseif( is_int( $index )) {
				$this->where("{$this->name}.{$column}", $value );
			} else {
				$this->where('0');
			}
		} else {
			$this->where("{$this->name}.{$column}", $value );
		}

		return $this;
	}

	/**
	 * @param string $index
	 * @param string $value
	 * @return array
	 */
	function fetchPairs( $index = null, $value = null ) : array
	{
		if( $index !== null and $value !== null ) {
			$assoc = function( &$row, &$key ) use( $value, $index ) {
				$key = $row[ $index ];
				$row = $row[ $value ];
			};
		} elseif( $value !== null ) {
			$assoc = function( &$row, &$key, $num ) use( $value ) {
				$key = $num;
				$row = $row[ $value ];
			};
		} elseif( $index !== null ) {
			$assoc = function( $row, &$key ) use( $index ) {
				$key = $row[ $index ];
			};
		} else {
			throw new InvalidArgumentException("Index or value must be provided.");
		}

		/** @var Selection $this */
		$query = new ModifyIterator( $this, $assoc );

		return $query->toArray();
	}

	/**
	 * @param int $limit
	 * @return ActiveRow[]
	 */
	function fetchLazy( int $limit ) : iterable
	{
		return new SelectionIterator( $this, $limit );
	}

	/**
	 * @return array
	 */
	function jsonSerialize() : array
	{
		$json = false;
		$data = [];

		/** @var Selection $this */
		foreach( $this as $row ) {
			$json = $row instanceof JsonSerializable;

			break;
		}

		/** @var JsonSerializable | ActiveRow $row */
		foreach( $this as $key => $row ) {
			$data[ $key ] = $json ? $row->jsonSerialize() : $row->toArray();
		}

		return $data;
	}
}
