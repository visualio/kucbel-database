<?php

namespace Kucbel\Database\Row;

use JsonSerializable;
use Kucbel\Database\Query\SelectionGroup;
use Nette;

/**
 * Class ActiveRow
 *
 * @method SelectionGroup		related( string $table, string $column = null )
 * @method ActiveRow|mixed|null	ref( string $key, string $throughColumn = null )
 */
class ActiveRow extends Nette\Database\Table\ActiveRow implements JsonSerializable
{
	/**
	 * @return static
	 */
	function reload() : self
	{
		$table = $this->getTable();
		$where = $this->getPrimary();

		$clone = $table->createSelectionInstance()
			->wherePrimary( $where )
			->fetch();

		if( !$clone instanceof $this ) {
			throw new Nette\InvalidStateException('Database refetch failed; row does not exist!');
		}

		return $clone;
	}

	/**
	 * @return mixed
	 * @internal
	 */
	final function jsonSerialize()
	{
		return $this->toJson();
	}

	/**
	 * @return array
	 */
	function toJson()
	{
		return $this->toArray();
	}
}
