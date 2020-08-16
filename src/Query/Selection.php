<?php

namespace Kucbel\Database\Query;

use JsonSerializable;
use Kucbel\Database\Repository;
use Kucbel\Database\Row\ActiveRow;
use Nette\Caching\IStorage;
use Nette\Database\Context;
use Nette\Database\IConventions;
use Nette\Database\Table;
use Nette\InvalidArgumentException;

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
	 * @param Context			$context
	 * @param IConventions		$conventions
	 * @param IStorage | null	$storage
	 * @param string			$table
	 */
	function __construct( Repository $repository, Context $context, IConventions $conventions, ?IStorage $storage, string $table )
	{
		parent::__construct( $context, $conventions, $table, $storage );

		$this->repository = $repository;
	}

	/**
	 * @return void
	 */
	protected function execute() : void
	{
		$this->detect();

		parent::execute();
	}

	/**
	 * @param string $columns
	 * @param mixed ...$params
	 * @return $this
	 */
	function select( $columns, ...$params )
	{
		if( !is_string( $columns )) {
			throw new InvalidArgumentException("Column must be a string.");
		}

		$this->verify( $columns );

		parent::select( $columns, ...$params );

		return $this;
	}

	/**
	 * @param string $word
	 * @param string $mode
	 * @return string
	 */
	static function like( string $word, string $mode = null ) : string
	{
		$word = addcslashes( $word, '\_%');

		switch( $mode ) {
			case null:
				return $word;
			case '>':
				return "{$word}%";
			case '<':
				return "%{$word}";
			case '?':
				return "%{$word}%";
			default:
				throw new InvalidArgumentException("Unknown flag '{$mode}'.");
		}
	}
}
