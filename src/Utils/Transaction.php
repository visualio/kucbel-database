<?php

namespace Kucbel\Database\Utils;

use Nette\Database\Context;
use Nette\SmartObject;
use Throwable;
use Tracy\ILogger;

class Transaction
{
	use SmartObject;

	/**
	 * @var Context
	 */
	private $database;

	/**
	 * @var ILogger
	 */
	private $logger;

	/**
	 * @var bool
	 */
	private $active = false;

	/**
	 * Transaction constructor.
	 *
	 * @param Context $database
	 * @param ILogger $logger
	 */
	function __construct( Context $database, ILogger $logger )
	{
		$this->database = $database;
		$this->logger = $logger;
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
		try {
			$this->begin();
			$result = $callback( ...$arguments );
			$this->commit();

			return $result;
		} catch( Throwable $error ) {
			if( $this->active ) {
				$this->revert();
			}

			throw $error;
		}
	}

	/**
	 * @throws TransactionException
	 */
	function begin() : void
	{
		if( $this->active ) {
			throw new TransactionException('Transaction is already active.');
		}

		$this->database->beginTransaction();
		$this->active = true;
	}

	/**
	 * @throws TransactionException
	 */
	function commit() : void
	{
		if( !$this->active ) {
			throw new TransactionException("Transaction wasn't started.");
		}

		$this->database->commit();
		$this->active = false;
	}

	/**
	 * @throws TransactionException
	 */
	function revert() : void
	{
		if( !$this->active ) {
			throw new TransactionException("Transaction wasn't started.");
		}

		$this->database->rollBack();
		$this->active = false;
	}

	/**
	 * @throws TransactionException
	 */
	function ensure() : void
	{
		if( !$this->active ) {
			throw new TransactionException("Transaction is required.");
		}
	}

	/**
	 * @param Throwable $error
	 * @param string $level
	 */
	function log( Throwable $error, string $level = ILogger::EXCEPTION )
	{
		$this->logger->log( $error, $level );
	}
}
