<?php

namespace Kucbel\Database\Query;

use Nette\Database\Table\Selection;

trait Relation
{
	/**
	 * @param string $table
	 * @param string $column
	 * @return Selection
	 */
	abstract function related( $table, $column = null );
}