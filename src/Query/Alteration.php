<?php

namespace Kucbel\Database\Query;

use JsonSerializable;
use Kucbel\Database\Repository;
use Kucbel\Database\Row\ActiveRow;
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
	function createRow( array $row ) : Nette\Database\Table\ActiveRow
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
