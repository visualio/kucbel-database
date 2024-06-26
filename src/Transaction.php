<?php

namespace Kucbel\Database;

use Nette\Database\Connection;
use Nette\InvalidStateException;
use Nette\SmartObject;
use Throwable;
use Tracy\ILogger;

class Transaction
{
	use SmartObject, Trigger;

	/**
	 * @var Connection
	 */
	private $connection;

	/**
	 * @var ILogger | null
	 */
	private $logger;

	/**
	 * @var bool
	 */
	private $active = false;

	/**
	 * Transaction constructor.
	 *
	 * @param Connection $connection
	 */
	function __construct( Connection $connection )
	{
		$this->connection = $connection;
	}

	/**
	 * Transaction destructor.
	 */
	function __destruct()
	{
		if( $this->active ) {
			$this->revert();
		}
	}

	/**
	 * @param callable $callback
	 * @param mixed ...$arguments
	 * @return mixed
	 * @throws mixed
	 */
	function wrap( callable $callback, ...$arguments )
	{
		$this->begin();

		try {
			$result = $callback( ...$arguments );
		} catch( Throwable $error ) {
			$this->revert();

			if( $this->logger ) {
				$this->logger->log( $error, ILogger::EXCEPTION );
			}

			throw $error;
		}

		$this->commit();

		return $result;
	}

	/**
	 * @throws
	 */
	function begin() : void
	{
		if( $this->active ) {
			throw new InvalidStateException('Transaction is already active.');
		}

		$this->dispatch('pre-begin');

		$this->connection->beginTransaction();
		$this->active = true;

		$this->dispatch('post-begin');
	}

	/**
	 * @throws
	 */
	function commit() : void
	{
		if( !$this->active ) {
			throw new InvalidStateException("Transaction wasn't started.");
		}

		$this->dispatch('pre-commit');

		$this->connection->commit();
		$this->active = false;

		$this->dispatch('post-commit');
	}

	/**
	 * @throws
	 */
	function revert() : void
	{
		if( !$this->active ) {
			throw new InvalidStateException("Transaction wasn't started.");
		}

		$this->dispatch('pre-revert');

		$this->connection->rollBack();
		$this->active = false;

		$this->dispatch('post-revert');
	}

	/**
	 * @throws
	 */
	function ensure() : void
	{
		if( !$this->active ) {
			throw new InvalidStateException("Transaction is required.");
		}
	}

	/**
	 * @param ILogger $logger
	 */
	function setLogger( ILogger $logger ) : void
	{
		$this->logger = $logger;
	}

	/**
	 * @return bool
	 */
	function isActive() : bool
	{
		return $this->active;
	}
}
