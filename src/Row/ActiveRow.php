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
