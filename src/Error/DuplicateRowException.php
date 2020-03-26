<?php

namespace Kucbel\Database\Error;

use Kucbel\Database\Exception;
use Kucbel\Database\Row\ActiveRow;
use Throwable;

class DuplicateRowException extends Exception
{
	/**
	 * @var ActiveRow | null
	 */
	private $duplicate;

	/**
	 * DuplicateRowException constructor.
	 *
	 * @param ActiveRow $duplicate
	 * @param string $message
	 * @param int $code
	 * @param Throwable $previous
	 */
	function __construct( ActiveRow $duplicate, string $message = null, int $code = null, Throwable $previous = null )
	{
		parent::__construct( $message, $code, $previous );

		$this->duplicate = $duplicate;
	}

	/**
	 * @return ActiveRow | mixed
	 */
	function getDuplicate() : ActiveRow
	{
		return $this->duplicate;
	}
}
