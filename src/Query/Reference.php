<?php

namespace Kucbel\Database\Query;

use Kucbel\Database\Row\ActiveRow;
use Nette;

trait Reference
{
	/**
	 * @param string $table
	 * @param string $column
	 * @return ActiveRow | mixed | null
	 */
	abstract function ref( string $table, string $column = null ) : ?Nette\Database\Table\IRow;
}
