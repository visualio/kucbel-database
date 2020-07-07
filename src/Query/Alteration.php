<?php

namespace Kucbel\Database\Query;

use JsonSerializable;
use Kucbel\Database\Repository;
use Kucbel\Database\Row\ActiveRow;
use Kucbel\Iterators\ModifyIterator;
use Nette\InvalidArgumentException;
use Nette;

trait Alteration
{
	/**
	 * @var Repository
	 */
	protected $deposit;

	/**
	 * @var string
	 */
	protected $record;

	/**
	 * @param array $row
	 * @return ActiveRow
	 */
	protected function createRow( array $row ) : Nette\Database\Table\ActiveRow
	{
		return new $this->record( $row, $this );
	}

	/**
	 * @param string $table
	 * @return Selection
	 */
	function createSelectionInstance( string $table = null ) : Nette\Database\Table\Selection
	{
		/** @var Selection $this */
		return new Selection( $this->deposit, $this->context, $this->conventions, $this->cache ? $this->cache->getStorage() : null, $table ?? $this->name );
	}

	/**
	 * @param string $table
	 * @param string $column
	 * @return SelectionGroup
	 */
	protected function createGroupedSelectionInstance( $table, $column ) : Nette\Database\Table\GroupedSelection
	{
		/** @var Selection $this */
		return new SelectionGroup(  $this->deposit, $this->context, $this->conventions, $this, $this->cache ? $this->cache->getStorage() : null, $table, $column );
	}

	/**
	 * @param array | string $array
	 * @return array
	 */
	function format( $array ) : array
	{
		if( is_array( $array )) {
			if( !is_string( $value = current( $array ))) {
				throw new InvalidArgumentException("Array must contain string value.");
			}

			if( !is_string( $index = key( $array ))) {
				$index = null;
			}
		} elseif( is_string( $array )) {
			$value = $array;
			$index = null;
		} else {
			throw new InvalidArgumentException("Format must be either string or array.");
		}

		if( $index === null ) {
			$array = function( &$row, &$key, $num ) use( $value ) {
				$key = $num;
				$row = $row[ $value ];
			};
		} else {
			$array = function( &$row, &$key ) use( $value, $index ) {
				$key = $row[ $index ];
				$row = $row[ $value ];
			};
		}

		/** @var Selection $this */
		$query = new ModifyIterator( $this, $array );

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
