<?php

namespace Kucbel\Database\Driver;

use DateTimeInterface;
use Nette;

class MysqlDriver extends Nette\Database\Drivers\MySqlDriver
{
	/**
	 * @param DateTimeInterface $value
	 * @return string
	 */
	function formatDateTime( DateTimeInterface $value ) : string
	{
		return $value->format("'Y-m-d H:i:s.u'");
	}
}
