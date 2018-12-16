<?php

namespace Kucbel\Database\Table;

use Nette;
use Nette\Caching\IStorage;
use Nette\Database\IConventions;
use Kucbel\Database\Context;

class Selection extends Nette\Database\Table\Selection
{
	use SelectionTrait;

	/**
	 * Selection constructor.
	 *
	 * @param Context $context
	 * @param IConventions $conventions
	 * @param IStorage | null $storage
	 * @param string $table
	 */
	function __construct( Context $context, IConventions $conventions, ?IStorage $storage, string $table )
	{
		parent::__construct( $context, $conventions, $table, $storage );

		$this->rowClass = $context->getRowClass( $table );
	}
}