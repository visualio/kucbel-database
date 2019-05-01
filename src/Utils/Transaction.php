<?php

namespace Kucbel\Database\Utils;

use Nette\Database\Context;
use Nette\SmartObject;
use Throwable;

class Transaction
{
	use SmartObject;

	/**
	 * @var Context
	 */
	private $database;

	/**
	 * @var bool
	 */
	private $active = false;

	/**
	 * Transaction constructor.
	 *
	 * @param Context $database
	 */
	function __construct( Context $database )
	{
		$this->database = $database;
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
			$result = call_user_func( $callback, ...$arguments );
			$this->commit();
		} catch( Throwable $ex ) {
			if( $this->active ) {
				$this->revert();
			}

			throw $ex;
		}

		return $result;
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
	function revert()
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
	function ensure()
	{
		if( !$this->active ) {
			throw new TransactionException("Transaction is required.");
		}
	}
}