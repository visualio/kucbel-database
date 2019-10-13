<?php

namespace Kucbel\Database\Row;

use Nette\InvalidStateException;
use Throwable;

class DuplicateRowException extends ActiveRowException
{
	/**
	 * @var ActiveRow | null
	 */
	private $row;

	/**
	 * DuplicateRowException constructor.
	 *
	 * @param string $message
	 * @param int $code
	 * @param Throwable $previous
	 * @param ActiveRow $row
	 */
	function __construct( string $message = null, int $code = null, Throwable $previous = null, ActiveRow $row = null )
	{
		parent::__construct( $message, $code, $previous );

		$this->row = $row;
	}

	/**
	 * @return ActiveRow | mixed
	 */
	function getRow() : ActiveRow
	{
		if( !$this->row ) {
			throw new InvalidStateException("Duplicate row wasn't provided.");
		}

		return $this->row;
	}
}
