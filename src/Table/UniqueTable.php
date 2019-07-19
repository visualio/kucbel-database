<?php

namespace Kucbel\Database\Table;

use Kucbel\Database\Row\ActiveRow;
use Kucbel\Database\Row\DuplicateRowException;

abstract class UniqueTable extends Table
{
	/**
	 * @inheritdoc
	 */
	function insert( array $values )
	{
		$this->unique( null, $values );

		return parent::insert( $values );
	}

	/**
	 * @inheritdoc
	 */
	function update( ActiveRow $row, array $values ) : bool
	{
		$this->unique( $row, $values );

		return parent::update( $row, $values );
	}

	/**
	 * @param ActiveRow $row
	 * @param array $values
	 * @throws DuplicateRowException
	 */
	abstract function unique( ?ActiveRow $row, array $values ) : void;
}
