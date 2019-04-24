<?php

namespace Kucbel\Database\Table;

use Kucbel\Entity\AbstractFactory;

class TableFactory extends AbstractFactory
{
	/**
	 * @var Table[]
	 */
	private $tables;

	/**
	 * @param string $name
	 * @param bool $rude
	 * @return Table
	 */
	function get( string $name, bool $rude = true ) : Table
	{
		return $this->tables[ $name ] ?? $this->tables[ $name ] = $this->entity->create( Table::class, $name, $rude );
	}
}