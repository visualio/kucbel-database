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
	 * @var string | null
	 */
	protected $instance;

	/**
	 * @var int
	 */
	protected $metadata = 0;

	/**
	 * @return void
	 */
	protected function detect() : void
	{
		if( !$this->instance ) {
			if( $this->metadata === 0 or $this->metadata & 0b1 ) {
				$this->instance = $this->repository->getClass( $this->name );
			} else {
				$this->instance = $this->repository->getDefault();
			}
		}
	}

	/**
	 * @param string $columns
	 * @param array $params
	 */
	protected function verify( string $columns, array &$params ) : void
	{
		$this->instance = null;

		$force = array_search('force!', $params, true );

		if( $force !== false ) {
			$this->metadata |= 0b11;

			unset( $params[ $force ] );

			return;
		}

		if( $this->metadata & 0b1 ) {
			return;
		}

		$columns = explode(',', $columns );

		foreach( $columns as $column ) {
			$column = trim( $column );

			if( $column === '*' or $column === "{$this->name}.*") {
				$this->metadata |= 0b1;

				break;
			}
		}

		$this->metadata |= 0b10;
	}

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

			if( $first = $value[ 0 ] ?? null and is_array( $first )) {
				$this->where( $column, $value );
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
	 * @return mixed
	 */
	function jsonSerialize()
	{
		$test = true;
		$json = false;
		$data = [];

		/** @var Selection $this */
		foreach( $this as $key => $row ) {
			if( $test ) {
				$test = false;
				$json = $row instanceof JsonSerializable;
			}

			if( $json ) {
				/** @var JsonSerializable $row */
				$data[ $key ] = $row->jsonSerialize();
			} else {
				$data[ $key ] = $row->toArray();
			}
		}

		return $data;
	}
}
