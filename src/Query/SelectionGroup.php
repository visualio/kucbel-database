<?php

namespace Kucbel\Database\Query;

use JsonSerializable;
use Kucbel\Database\Explorer;
use Kucbel\Database\Repository;
use Kucbel\Database\Row\ActiveRow;
use Nette\Caching\Storage;
use Nette\Database\Conventions;
use Nette\Database\Table;
use Nette\Database\Table\Selection;

/**
 * Class SelectionGroup
 *
 * @method ActiveRow|mixed|null		fetch()
 * @method ActiveRow|mixed			current()
 */
class SelectionGroup extends Table\GroupedSelection implements JsonSerializable
{
	use Alteration;

	/**
	 * SelectionGroup constructor.
	 *
	 * @param Repository		$repository
	 * @param Explorer			$explorer
	 * @param Conventions		$conventions
	 * @param Selection			$reference
	 * @param Storage | null	$storage
	 * @param string			$table
	 * @param string			$column
	 */
	function __construct( Repository $repository, Explorer $explorer, Conventions $conventions, Selection $reference, ?Storage $storage, string $table, string $column )
	{
		parent::__construct( $explorer, $conventions, $table, $column, $reference, $storage );

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
