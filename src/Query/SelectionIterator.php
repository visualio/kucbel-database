<?php

namespace Kucbel\Database\Query;

use Iterator;
use Countable;
use Kucbel\Database\Row\ActiveRow;
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
	 * @var ActiveRow[]
	 */
	private $batch = [];

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
	private $abort = true;

	/**
	 * SelectionIterator constructor.
	 *
	 * @param Selection $query
	 * @param int       $limit
	 */
	function __construct( Selection $query, int $limit = 100 )
	{
		if( $limit < 1 ) {
			throw new InvalidArgumentException;
		}

		if( !strpos( $query->getSql(), 'ORDER BY')) {
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
		$query = $this->query->limit( $this->limit, $this->index );

		$batch = [];
		$count = 0;

		foreach( $query as $id => $row ) {
			$batch[ $id ] = $row;
			$count++;
		}

		$this->batch = $batch;
		$this->index += $count;

		if( $this->limit !== $count ) {
			$this->abort = true;
		}
	}

	/**
	 * @return void
	 */
	function rewind() : void
	{
		$this->index = 0;
		$this->abort = false;

		$this->fetch();
	}

	/**
	 * @return void
	 */
	function next() : void
	{
		$value = next( $this->batch );

		if( !$value and !$this->abort ) {
			$this->fetch();
		}
	}

	/**
	 * @return bool
	 */
	function valid() : bool
	{
		return key( $this->batch ) !== null;
	}

	/**
	 * @return mixed
	 */
	function key()
	{
		return key( $this->batch );
	}

	/**
	 * @return ActiveRow
	 */
	function current()
	{
		return current( $this->batch );
	}

	/**
	 * @return int
	 */
	function count() : int
	{
		return $this->count ?? $this->count = $this->query->count('*');
	}
}
