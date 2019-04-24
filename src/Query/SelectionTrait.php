<?php

namespace Kucbel\Database\Query;

use JsonSerializable;
use Nette\Database\Table\ActiveRow;

trait SelectionTrait
{
	/**
	 * @var string
	 */
	protected $rowClass;

	/**
	 * @param array $row
	 * @return ActiveRow
	 */
	function createRow( array $row )
	{
		return new $this->rowClass( $row, $this );
	}

	/**
	 * @param string $table
	 * @return Selection
	 */
	function createSelectionInstance( $table = null )
	{
		/** @var Selection $this */
		return new Selection( $this->context, $this->conventions, $this->cache ? $this->cache->getStorage() : null, $table ?? $this->name );
	}

	/**
	 * @param string $table
	 * @param string $column
	 * @return SelectionGroup
	 */
	protected function createGroupedSelectionInstance( $table, $column )
	{
		/** @var Selection $this */
		return new SelectionGroup( $this->context, $this->conventions, $this, $this->cache ? $this->cache->getStorage() : null, $table, $column );
	}

	/**
	 * @return mixed
	 */
	function jsonSerialize()
	{
		$json = [];
		$impl = null;

		foreach( $this as $key => $row ) {
			if( $impl ?? $impl = $row instanceof JsonSerializable ) {
				/** @var JsonSerializable $row */
				$json[ $key ] = $row->jsonSerialize();
			} else {
				/** @var ActiveRow $row */
				$json[ $key ] = $row->toArray();
			}
		}

		return $json;
	}
}