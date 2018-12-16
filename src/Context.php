<?php

namespace Kucbel\Database;

use Kucbel\Database\Table\Selection;
use Nette;
use Nette\Caching\IStorage;
use Nette\Database\Connection;
use Nette\Database\IConventions;
use Nette\Database\IStructure;
use Nette\Database\Table\ActiveRow;

class Context extends Nette\Database\Context
{
	/**
	 * @var Nette\Database\IConventions | null
	 */
	private $convert;

	/**
	 * @var Nette\Caching\IStorage | null
	 */
	private $storage;

	/**
	 * @var array | null
	 */
	private $classes;

	/**
	 * @var string
	 */
	private $default;

	/**
	 * Context constructor.
	 *
	 * @param Connection $connection
	 * @param IStructure $structure
	 * @param IConventions $conventions
	 * @param IStorage $storage
	 * @param array $classes
	 * @param string $default
	 */
	function __construct( Connection $connection, IStructure $structure, IConventions $conventions = null, IStorage $storage = null, array $classes = null, string $default = null )
	{
		parent::__construct( $connection, $structure, $conventions, $storage );

		$this->convert = $conventions;
		$this->storage = $storage;
		$this->classes = $classes;
		$this->default = $default ?? ActiveRow::class;
	}

	/**
	 * @param string $table
	 * @return Selection
	 */
	function table( $table )
	{
		return new Selection( $this, $this->convert, $this->storage, $table );
	}

	/**
	 * @param string $table
	 * @return string
	 */
	function getRowClass( $table )
	{
		return $this->classes[ $table ] ?? $this->default;
	}
}