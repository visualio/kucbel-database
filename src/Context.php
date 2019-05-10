<?php

namespace Kucbel\Database;

use Kucbel\Database\Query\Selection;
use Nette;
use Nette\Caching\IStorage;
use Nette\Database\Connection;
use Nette\Database\IConventions;
use Nette\Database\IStructure;

class Context extends Nette\Database\Context
{
	/**
	 * @var Repository
	 */
	protected $deposit;

	/**
	 * @var Nette\Database\IConventions | null
	 */
	private $convert;

	/**
	 * @var Nette\Caching\IStorage | null
	 */
	private $storage;

	/**
	 * Context constructor.
	 *
	 * @param Repository	$repository
	 * @param Connection	$connection
	 * @param IStructure	$structure
	 * @param IConventions	$conventions
	 * @param IStorage		$storage
	 */
	function __construct( Repository $repository, Connection $connection, IStructure $structure, IConventions $conventions = null, IStorage $storage = null )
	{
		parent::__construct( $connection, $structure, $conventions, $storage );

		$this->deposit = $repository;
		$this->convert = $conventions;
		$this->storage = $storage;
	}

	/**
	 * @param string $table
	 * @return Selection
	 */
	function table( $table )
	{
		return new Selection( $this->deposit, $this, $this->convert, $this->storage, $table );
	}
}