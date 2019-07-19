<?php

namespace Kucbel\Database\Query;

use Countable;
use Iterator;
use Kucbel\Database\Row\ActiveRow;
use Nette\InvalidArgumentException;
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
	private $limit;

	/**
	 * @var int
	 */
	private $index = 0;

	/**
	 * @var int | null
	 */
	private $count;

	/**
	 * @var bool
	 */
	private $exist = false;

	/**
	 * Paginator constructor.
	 *
	 * @param Selection $query
	 * @param int       $limit
	 */
	function __construct( Selection $query, int $limit = 100 )
	{
		if( $limit < 1 ) {
			throw new InvalidArgumentException;
		}

		if( !$query->getSqlBuilder()->getOrder() ) {
			$primary = (array) $query->getPrimary();

			$query->order( implode(', ', $primary ));
		}

		$this->query = $query;
		$this->limit = $limit;
	}

	/**
	 * @return void
	 */
	protected function fetch() : void
	{
		if( $this->exist ) {
			$this->query->limit( $this->limit, $this->index );
			$this->query->rewind();

			$this->index += $this->limit;

			if( $this->query->count() !== $this->limit ) {
				$this->exist = false;
			}
		} else {
			$this->query->limit( null );
		}
	}

	/**
	 * @return void
	 */
	function rewind() : void
	{
		$this->index = 0;
		$this->exist = true;

		$this->fetch();
	}

	/**
	 * @return void
	 */
	function next() : void
	{
		$this->query->next();

		if( !$this->query->valid() ) {
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
	 * @return mixed
	 */
	function key()
	{
		return $this->query->key();
	}

	/**
	 * @return ActiveRow | false
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
