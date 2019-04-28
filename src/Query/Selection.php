<?php

namespace Kucbel\Database\Query;

use JsonSerializable;
use Kucbel\Database\Context;
use Nette\Caching\IStorage;
use Nette\Database\IConventions;
use Nette\Database\Table;

class Selection extends Table\Selection implements JsonSerializable
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