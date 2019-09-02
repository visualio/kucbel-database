<?php

namespace Kucbel\Database\Query;

use Nette;

trait Relation
{
	/**
	 * @param string $table
	 * @param string $column
	 * @return SelectionGroup
	 */
	abstract function related( string $table, string $column = null ) : Nette\Database\Table\GroupedSelection;
}
