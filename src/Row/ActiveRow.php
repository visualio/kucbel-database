<?php

namespace Kucbel\Database\Row;

use JsonSerializable;
use Nette;

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
