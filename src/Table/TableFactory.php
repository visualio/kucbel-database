<?php

namespace Kucbel\Database\Table;

interface TableFactory
{
	/**
	 * @param string $name
	 * @param array $options
	 * @param array $defaults
	 * @return Table
	 */
	function create( string $name, array $options = null, array $defaults = null ) : Table;
}
