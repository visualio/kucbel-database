<?php

namespace Kucbel\Database\Utils;

use Nette\StaticClass;

class Escape
{
	use StaticClass;
	
	/**
	 * @param string $word
	 * @return string
	 */
	static function like( string $word )
	{
		return addcslashes( $word, '\_%');
	}
}