<?php

namespace Kucbel\Database\Query;

use JsonSerializable;
use Kucbel\Database\Repository;
use Nette\Caching\IStorage;
use Nette\Database\Context;
use Nette\Database\IConventions;
use Nette\Database\Table;
use Nette\Database\Table\Selection;

class SelectionGroup extends Table\GroupedSelection implements JsonSerializable
{
	use Alteration;

	/**
	 * SelectionGroup constructor.
	 *
	 * @param Repository		$repository
	 * @param Context			$context
	 * @param IConventions		$conventions
	 * @param Selection			$reference
	 * @param IStorage | null	$storage
	 * @param string			$table
	 * @param string			$column
	 */
	function __construct( Repository $repository, Context $context, IConventions $conventions, Selection $reference, ?IStorage $storage, string $table, string $column )
	{
		parent::__construct( $context, $conventions, $table, $column, $reference, $storage );

		$this->deposit = $repository;
		$this->record = $repository->getClass( $table );
	}

	/**
	 * @param string $columns
	 * @param mixed ...$params
	 * @return $this
	 */
	function select( $columns, ...$params )
	{
		if( $columns !== '*' and $columns !== "{$this->name}.*") {
			$this->record = $this->deposit->getDefault();

			return parent::select( $columns, ...$params );
		} else {
			return Selection::select("{$this->name}.*", ...$params );
		}
	}
}