<?php

namespace Kucbel\Database\Query;

use Nette\Database\Table\SqlBuilder;

class Builder extends SqlBuilder
{
	/**
	 * @param SqlBuilder $builder
	 */
	static function clrAlias( SqlBuilder $builder ) : void
	{
		$builder->aliases = [];
	}

	/**
	 * @param SqlBuilder $builder
	 */
	static function clrSelect( SqlBuilder $builder ) : void
	{
		$builder->select =
		$builder->parameters['select'] = [];
	}

	/**
	 * @param SqlBuilder $builder
	 */
	static function clrJoin( SqlBuilder $builder ) : void
	{
		$builder->joinCondition =
		$builder->parameters['joinCondition'] = [];
		$builder->parameters['joinConditionSorted'] = null;
	}

	/**
	 * @param SqlBuilder $builder
	 */
	static function clrWhere( SqlBuilder $builder ) : void
	{
		$builder->where =
		$builder->parameters['where'] = [];
	}

	/**
	 * @param SqlBuilder $builder
	 */
	static function clrGroup( SqlBuilder $builder ) : void
	{
		$builder->group = '';
		$builder->parameters['group'] = [];
	}

	/**
	 * @param SqlBuilder $builder
	 */
	static function clrHaving( SqlBuilder $builder ) : void
	{
		$builder->having = '';
		$builder->parameters['having'] = [];
	}

	/**
	 * @param SqlBuilder $builder
	 */
	static function clrOrder( SqlBuilder $builder ) : void
	{
		$builder->order =
		$builder->parameters['order'] = [];
	}
}