<?php

namespace Kucbel\Database;

use Nette\Caching\IStorage;
use Nette\Database\IConventions;
use Nette\Database\Table;
use Nette\Database\Table\Selection;

class SelectionGroup extends Table\GroupedSelection
{
	use SelectionTrait;

	/**
	 * SelectionGroup constructor.
	 *
	 * @param Context $context
	 * @param IConventions $conventions
	 * @param Selection $reference
	 * @param IStorage | null $storage
	 * @param string $table
	 * @param string $column
	 */
	function __construct( Context $context, IConventions $conventions, Selection $reference, ?IStorage $storage, string $table, string $column )
	{
		parent::__construct( $context, $conventions, $table, $column, $reference, $storage );

		$this->rowClass = $context->getRowClass( $table );
	}
}