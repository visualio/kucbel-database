<?php

namespace Kucbel\Database\Table;

interface TableFactory
{
	/**
	 * @param string $table
	 * @param array $options
	 * @param array $defaults
	 * @return Table
	 */
	function create( string $table, array $options = null, array $defaults = null ) : Table;
}
