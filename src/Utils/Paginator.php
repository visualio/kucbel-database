<?php

namespace Kucbel\Database\Utils;

use Countable;
use Iterator;
use Nette\Database\Table\ActiveRow;
use Nette\Database\Table\Selection;
use Nette\InvalidArgumentException;
use Nette\InvalidStateException;
use Nette\SmartObject;

class Paginator implements Countable, Iterator
{
	use SmartObject;

	/**
	 * @var Selection
	 */
	private $query;

	/**
	 * @var int
	 */
	private $fetch;

	/**
	 * @var int
	 */
	private $index = 0;

	/**
	 * @var int
	 */
	private $queue = 0;

	/**
	 * @var int | null
	 */
	private $limit;

	/**
	 * @var int | null
	 */
	private $count;

	/**
	 * Paginator constructor.
	 *
	 * @param Selection $query
	 * @param int       $fetch
	 */
	function __construct( Selection $query, int $fetch = 100 )
	{
		if( $fetch < 1 ) {
			throw new InvalidArgumentException;
		}

		$build = $query->getSqlBuilder();

		$index = $build->getOffset();
		$limit = $build->getLimit();
		$order = $build->getOrder();

		if( $index ) {
			throw new InvalidStateException("Query can't use offset.");
		} elseif( $limit and $limit < 0 ) {
			throw new InvalidStateException("Query can't use negative limit.");
		}

		if( !$order ) {
			$order = (array) $query->getPrimary();

			$query->order( implode(', ', $order ));
		}

		$this->query = $query;
		$this->fetch = $fetch;
		$this->limit = $limit;
	}

	/**
	 * @return void
	 */
	protected function fetch() : void
	{
		if( !$this->limit ) {
			$fetch = $this->fetch;
		} elseif(( $fetch = $this->limit - $this->queue ) > $this->fetch ) {
			$fetch = $this->fetch;
		}

		$this->query->limit( $fetch, $this->queue );
		$this->query->rewind();

		if( $this->query->valid() ) {
			$this->index++;
		}

		$this->queue += $this->fetch;

		if( $this->limit and $this->limit <= $this->queue ) {
			$this->queue++;
		}
	}

	/**
	 * @return void
	 */
	function rewind() : void
	{
		$this->index = 
		$this->queue = 0;

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
		} elseif( $this->index === $this->queue ) {
			$this->fetch();
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
