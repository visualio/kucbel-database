<?php

namespace Kucbel\Database;

use Nette\Database\Table\ActiveRow;
use Nette\SmartObject;

class Repository
{
	use SmartObject;

	/**
	 * @var string[] | null
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
	function __construct( array $classes = null, string $default = null )
	{
		$this->classes = $classes;
		$this->default = $default ?? ActiveRow::class;
	}

	/**
	 * @param string $table
	 * @return string
	 */
	function getClass( string $table ) : string
	{
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