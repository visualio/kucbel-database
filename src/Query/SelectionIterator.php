<?php

namespace Kucbel\Database\Query;

use Countable;
use Iterator;
use Nette\Database\Table\ActiveRow;
use Nette\Database\Table\Selection;
use Nette\InvalidStateException;
use Nette\SmartObject;

class SelectionIterator implements Countable, Iterator
{
	use SmartObject;

	/**
	 * @var Selection
	 */
	private $query;

	/**
	 * @var int
	 */
	private $index = 0;

	/**
	 * @var int
	 */
	private $final = 0;

	/**
	 * @var int
	 */
	private $limit;

	/**
	 * @var int | null
	 */
	private $count;

	/**
	 * SelectionIterator constructor.
	 *
	 * @param Selection $query
	 */
	function __construct( Selection $query )
	{
		$build = $query->getSqlBuilder();

		$first = $build->getOffset();
		$limit = $build->getLimit();
		$order = $build->getOrder();

		if( !$limit ) {
			$limit = 100;
		}

		if( $first ) {
			throw new InvalidStateException("Query can't use offset.");
		} elseif( $limit < 1 ) {
			throw new InvalidStateException("Query can't use negative limit.");
		}

		if( !$order ) {
			$order = (array) $query->getPrimary();

			$query->order( implode(', ', $order ));
		}

		$this->query = $query;
		$this->limit = $limit;
	}

	/**
	 * @return void
	 */
	protected function fetch() : void
	{
		$this->query->limit( $this->limit, $this->final );
		$this->query->rewind();

		if( $this->query->valid() ) {
			$this->index++;
		}

		$this->final += $this->limit;
	}

	/**
	 * @return void
	 * @todo separate query prototype & data iterator
	 */
	protected function clear() : void
	{
		foreach( $this->query as $id => $row ) {
			unset( $this->query[ $id ] );
		}
	}

	/**
	 * @return void
	 */
	function rewind() : void
	{
		$this->index =
		$this->final = 0;

		$this->fetch();
	}

	/**
	 * @return void
	 */
	function next() : void
	{
		$this->query->next();

		if( $this->query->valid() ) {
			$this->index++;
		} elseif( $this->index === $this->final ) {
			$this->fetch();
		} else {
			$this->clear();
		}
	}

	/**
	 * @return bool
	 */
	function valid() : bool
	{
		return $this->query->valid();
	}

	/**
	 * @return string | int
	 */
	function key()
	{
		return $this->query->key();
	}

	/**
	 * @return ActiveRow
	 */
	function current()
	{
		return $this->query->current();
	}

	/**
	 * @return int
	 */
	function count() : int
	{
		return $this->count ?? $this->count = $this->query->count('*');
	}
}
