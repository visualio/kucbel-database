<?php

namespace Kucbel\Database;

use RuntimeException;
use Throwable;

abstract class Exception extends RuntimeException
{
	/**
	 * Exception constructor.
	 *
	 * @param string | null $message
	 * @param int | null $code
	 * @param Throwable | null $previous
	 */
	function __construct( $message = null, $code = null, Throwable $previous = null )
	{
		parent::__construct( $message ?? '', $code ?? 0, $previous );
	}
}
