<?php

namespace Kucbel\Database;

use Nette\Database\SqlLiteral;

class Literal extends SqlLiteral
{
	/**
	 * Literal constructor.
	 *
	 * @param string $query
	 * @param mixed ...$values
	 */
	function __construct( string $query, ...$values )
	{
		parent::__construct( $query, $values );
	}
}
