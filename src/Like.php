<?php

namespace Kucbel\Database;

use Nette\InvalidArgumentException;
use Nette\StaticClass;

class Like
{
	use StaticClass;

	const
		MATCHING	= '?',
		STARTING	= '>',
		ENDING		= '<';

	/**
	 * @param string $value
	 * @param string | null $query
	 * @return string
	 */
	static function escape( string $value, string $query = null ) : string
	{
		$value = addcslashes( $value, '\_%');

		switch( $query ) {
			case null:
				return $value;
			case self::STARTING;
				return "{$value}%";
			case self::ENDING;
				return "%{$value}";
			case self::MATCHING;
				return "%{$value}%";
			default:
				throw new InvalidArgumentException('Invalid query flag.');
		}
	}
}