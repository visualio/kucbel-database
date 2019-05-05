<?php

namespace Kucbel\Database\Query;

use Nette\Database\Table\ActiveRow;

trait Reference
{
	/**
	 * @param string $table
	 * @param string $column
	 * @return ActiveRow | null
	 */
	abstract function ref( $table, $column = null );
}