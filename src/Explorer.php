<?php

namespace Kucbel\Database;

use Kucbel\Database\Query\Selection;
use Nette;
use Nette\Caching\Storage;
use Nette\Database\Connection;
use Nette\Database\Conventions;
use Nette\Database\IStructure;

class Explorer extends Nette\Database\Explorer
{
	/**
	 * @var Repository
	 */
	protected $deposit;

	/**
	 * @var Conventions | null
	 */
	private $convert;

	/**
	 * @var Storage | null
	 */
	private $storage;

	/**
	 * Context constructor.
	 *
	 * @param Repository	$repository
	 * @param Connection	$connection
	 * @param IStructure	$structure
	 * @param Conventions	$conventions
	 * @param Storage		$storage
	 */
	function __construct( Repository $repository, Connection $connection, IStructure $structure, Conventions $conventions = null, Storage $storage = null )
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
	function table( string $table ) : Nette\Database\Table\Selection
	{
		return new Selection( $this->deposit, $this, $this->convert, $this->storage, $table );
	}
}
