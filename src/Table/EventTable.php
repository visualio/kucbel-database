<?php

namespace Kucbel\Database\Table;

use Nette\Database\Table\ActiveRow;
use Nette\Utils\Callback;

abstract class EventTable extends Table
{
	/**
	 * @var array
	 */
	private $listens;

	/**
	 * @param string $event
	 * @param callable $method
	 * @return $this
	 */
	function subscribe( string $event, callable $method )
	{
		$this->listens[ $event ][] = Callback::check( $method );

		return $this;
	}

	/**
	 * @param string $event
	 * @param mixed ...$values
	 */
	protected function dispatch( string $event, &...$values ) : void
	{
		if( $listens = $this->listens[ $event ] ?? null ) {
			foreach( $listens as $listen ) {
				$listen( ...$values );
			}
		}
	}

	/**
	 * @inheritDoc
	 */
	function insert( array $values ) : ActiveRow
	{
		$this->dispatch('pre-insert', $values );

		$insert = parent::insert( $values );

		$this->dispatch('post-insert', $insert );

		return $insert;
	}

	/**
	 * @inheritDoc
	 */
	function insertOne( array $values ) : int
	{
		$this->dispatch('pre-insert-one', $values );

		$insert = parent::insertOne( $values );

		$this->dispatch('post-insert-one', $insert );

		return $insert;
	}

	/**
	 * @inheritDoc
	 */
	function insertMany( iterable $values, int $batch = null ) : int
	{
		$this->dispatch('pre-insert-many', $values );

		$insert = parent::insertMany( $values, $batch );

		$this->dispatch('pre-insert-many', $insert );

		return $insert;
	}

	/**
	 * @inheritDoc
	 */
	function update( ActiveRow $row, array $values ) : bool
	{
		$this->dispatch('pre-update', $row, $values );

		$update = parent::update( $row, $values );

		$this->dispatch('post-update', $row, $update );

		return $update;
	}

	/**
	 * @inheritDoc
	 */
	function updateMany( array $values, array $where = null, array $order = null, int $limit = null ) : int
	{
		$this->dispatch('pre-update-many', $values );

		$update = parent::updateMany( $values, $where, $order, $limit );

		$this->dispatch('post-update-many', $update );

		return $update;
	}

	/**
	 * @inheritDoc
	 */
	function updateAll( array $values, array $order = null ) : int
	{
		$this->dispatch('pre-update-all', $values );

		$update = parent::updateAll( $values, $order );

		$this->dispatch('post-update-all', $update );

		return $update;
	}

	/**
	 * @inheritDoc
	 */
	function delete( ActiveRow $row ) : bool
	{
		$this->dispatch('pre-delete', $row );

		$delete = parent::delete( $row );

		$this->dispatch('post-delete', $delete );

		return $delete;
	}

	/**
	 * @inheritDoc
	 */
	function deleteMany( array $where = null, array $order = null, int $limit = null ) : int
	{
		$this->dispatch('pre-delete-many');

		$delete = parent::deleteMany( $where, $order, $limit );

		$this->dispatch('post-delete-many', $delete );

		return $delete;
	}

	/**
	 * @inheritDoc
	 */
	function deleteAll() : int
	{
		$this->dispatch('pre-delete-all');

		$delete = parent::deleteAll();

		$this->dispatch('post-delete-all', $delete );

		return $delete;
	}
}
