<?php

namespace Kucbel\Database\Table;

interface TableFactory
{
	/**
	 * @param string $table
	 * @param array | null $options
	 * @param array | null $defaults
	 * @return Table
	 */
	function create( string $table, array $options = null, array $defaults = null ) : Table;
}
