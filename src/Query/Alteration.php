<?php

namespace Kucbel\Database\Query;

use JsonSerializable;
use Kucbel\Database\Repository;
use Nette\Database\Table\ActiveRow;

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
	function createRow( array $row )
	{
		return new $this->record( $row, $this );
	}

	/**
	 * @param string $table
	 * @return Selection
	 */
	function createSelectionInstance( $table = null )
	{
		/** @var Selection $this */
		return new Selection( $this->deposit, $this->context, $this->conventions, $this->cache ? $this->cache->getStorage() : null, $table ?? $this->name );
	}

	/**
	 * @param string $table
	 * @param string $column
	 * @return SelectionGroup
	 */
	protected function createGroupedSelectionInstance( $table, $column )
	{
		/** @var Selection $this */
		return new SelectionGroup(  $this->deposit, $this->context, $this->conventions, $this, $this->cache ? $this->cache->getStorage() : null, $table, $column );
	}

	/**
	 * @return mixed
	 */
	function jsonSerialize()
	{
		$data = [];
		$json = null;

		foreach( $this as $key => $row ) {
			if( $json ?? $json = $row instanceof JsonSerializable ) {
				/** @var JsonSerializable $row */
				$data[ $key ] = $row->jsonSerialize();
			} else {
				/** @var ActiveRow $row */
				$data[ $key ] = $row->toArray();
			}
		}

		return $data;
	}
}