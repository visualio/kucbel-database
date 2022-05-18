<?php

namespace Kucbel\Database\Query;

use Countable;
use Iterator;
use Kucbel\Iterators\VoidIterator;
use Nette\Database\Table\ActiveRow;
use Nette\Database\Table\Selection;
use Nette\InvalidArgumentException;
use Nette\SmartObject;

class SelectionIterator implements Countable, Iterator
{
	use SmartObject;

	/**
	 * @var Selection
	 */
	private $query;

	/**
	 * @var Iterator
	 */
	private $cache;

	/**
	 * @var Iterator
	 */
	private $empty;

	/**
	 * @var int
	 */
	private $limit;

	/**
	 * @var int
	 */
	private $index = 0;

	/**
	 * @var int
	 */
	private $final = 0;

	/**
	 * @var int | null
	 */
	private $count = null;

	/**
	 * SelectionIterator constructor.
	 *
	 * @param Selection $query
	 * @param int $limit
	 */
	function __construct( Selection $query, int $limit )
	{
		if( $limit < 1 ) {
			throw new InvalidArgumentException("Limit must be greater than zero.");
		}

		$build = $query->getSqlBuilder();
		$order = $build->getOrder();
		$table = $build->getTableName();

		if( !$order ) {
			$order = implode(", {$table}.", (array) $query->getPrimary() );

			$query->order("{$table}.{$order}");
		}

		$this->query = $query;
		$this->limit = $limit;

		$this->cache =
		$this->empty = new VoidIterator;
	}

	/**
	 * SelectionIterator cloner.
	 */
	function __clone()
	{
		$this->index =
		$this->final = 0;

		$this->cache = $this->empty;
	}

	/**
	 * @return Iterator | null
	 */
	protected function fetch() : ?Iterator
	{
		$query = clone $this->query;
		$query->limit( $this->limit, $this->final );
		$query->rewind();

		$this->final += $this->limit;

		return $query->valid() ? $query : null;
	}

	/**
	 * @return void
	 */
	function rewind() : void
	{
		$this->index =
		$this->final = 0;

		if( $this->cache = $this->fetch() ) {
			$this->index++;
		} else {
			$this->cache = $this->empty;
		}
	}

	/**
	 * @return void
	 */
	function next() : void
	{
		$this->cache->next();

		if( $this->cache->valid() ) {
			$this->index++;
		} elseif( $this->index === $this->final and $this->cache = $this->fetch() ) {
			$this->index++;
		} else {
			$this->cache = $this->empty;
		}
	}

	/**
	 * @return bool
	 */
	function valid() : bool
	{
		return $this->cache->valid();
	}

	/**
	 * @return string | int
	 */
	function key()
	{
		return $this->cache->key();
	}

	/**
	 * @return ActiveRow
	 */
	function current()
	{
		return $this->cache->current();
	}

	/**
	 * @return int
	 */
	function count() : int
	{
		return $this->count ?? $this->count = $this->query->count('*');
	}
}
