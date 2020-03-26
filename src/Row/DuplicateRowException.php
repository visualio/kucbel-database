<?php

namespace Kucbel\Database\Row;

use Throwable;

class DuplicateRowException extends ActiveRowException
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
