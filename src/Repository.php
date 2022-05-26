<?php

namespace Kucbel\Database;

use Nette\SmartObject;

class Repository
{
	use SmartObject;

	/**
	 * @var string[]
	 */
	private $classes;

	/**
	 * @var string
	 */
	private $default;

	/**
	 * Repository constructor.
	 *
	 * @param string[] $classes
	 * @param string $default
	 */
	function __construct( array $classes, string $default )
	{
		$this->classes = $classes;
		$this->default = $default;
	}

	/**
	 * @param string $table
	 * @return string
	 */
	function getClass( string $table ) : string
	{
		if( $index = strrpos( $table, '.')) {
			$table = substr( $table, $index + 1 );
		}

		return $this->classes[ $table ] ?? $this->default;
	}

	/**
	 * @return string
	 */
	function getDefault() : string
	{
		return $this->default;
	}
}
