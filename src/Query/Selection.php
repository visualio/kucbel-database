<?php

namespace Kucbel\Database\Query;

use JsonSerializable;
use Kucbel\Database\Explorer;
use Kucbel\Database\Repository;
use Kucbel\Database\Row\ActiveRow;
use Nette\Caching\Storage;
use Nette\Database\Conventions;
use Nette\Database\Table;

/**
 * Class Selection
 *
 * @method ActiveRow|mixed|null		fetch()
 * @method ActiveRow|mixed			current()
 */
class Selection extends Table\Selection implements JsonSerializable
{
	use Alteration;

	/**
	 * Selection constructor.
	 *
	 * @param Repository		$repository
	 * @param Explorer			$explorer
	 * @param Conventions		$conventions
	 * @param Storage | null	$storage
	 * @param string			$table
	 */
	function __construct( Repository $repository, Explorer $explorer, Conventions $conventions, Storage | null $storage, string $table )
	{
		parent::__construct( $explorer, $conventions, $table, $storage );

		$this->repository = $repository;
		$this->instance = $repository->getClass( $table );
	}

	/**
	 * @param string $columns
	 * @param mixed ...$params
	 * @return $this
	 */
	function select( $columns, ...$params )
	{
		if( $columns !== '*' and $columns !== "{$this->name}.*") {
			$this->instance = $this->repository->getDefault();
		}

		parent::select( $columns, ...$params );

		return $this;
	}
}
