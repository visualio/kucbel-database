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
		$json = false;

		/** @var Selection $this */
		foreach( $this as $key => $row ) {
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