<?php

namespace Kucbel\Database\DI;

use Kucbel;
use Kucbel\Scalar\Input\DirectInput;
use Kucbel\Scalar\Input\ExtensionInput;
use Nette;
use Nette\DI\CompilerExtension;
use Nette\Loaders\RobotLoader;
use ReflectionClass;
use ReflectionException;

class DatabaseExtension extends CompilerExtension
{
	/**
	 * Config
	 */
	function loadConfiguration()
	{
		$builder = $this->getContainerBuilder();

		$builder->addDefinition( $this->prefix('registry'))
			->setType( Kucbel\Database\Repository::class );

		$builder->addDefinition( $this->prefix('table'))
			->setType( Kucbel\Database\Table\Table::class )
			->addTag('entity');

		$builder->addDefinition( $this->prefix('table.factory'))
			->setType( Kucbel\Database\Table\TableFactory::class )
			->setInject();

		$builder->addDefinition( $this->prefix('utils.trans'))
			->setType( Kucbel\Database\Utils\Transaction::class )
			->setInject();
	}

	/**
	 * Compile
	 *
	 * @throws ReflectionException
	 */
	function beforeCompile()
	{
		$param = $this->getTableParams();

		if( $param['scan'] ) {
			$classes = $this->getTableClasses( $param['scan'], $param['const'] );
		} else {
			$classes = null;
		}

		$builder = $this->getContainerBuilder();

		$builder->getDefinition( $registry = $this->prefix('registry'))
			->setArguments([ $classes, $param['row'] ]);

		foreach( $builder->findByType( Nette\Database\Context::class ) as $service ) {
			$factory = $service->getFactory();
			$arguments = $factory->arguments ?? [];

			array_unshift( $arguments, "@$registry");

			$service->setType( Kucbel\Database\Context::class );
			$service->setFactory( Kucbel\Database\Context::class, $arguments );
		}

		foreach( $builder->findByType( Kucbel\Database\Table\Table::class ) as $service ) {
			$service->setInject();
		}
	}

	/**
	 * @param array $scan
	 * @param string $const
	 * @return array
	 * @throws ReflectionException
	 */
	private function getTableClasses( array $scan, string $const ) : array
	{
		$robot = new RobotLoader;
		$robot->addDirectory( $scan );
		$robot->rebuild();

		$classes = [];

		foreach( $robot->getIndexedClasses() as $class => $void ) {
			$ref = new ReflectionClass( $class );

			if( !$ref->isSubclassOf( Nette\Database\Table\IRow::class ) or !$ref->isInstantiable() or !$ref->hasConstant( $const ) ) {
				continue;
			}

			$input = new DirectInput([ $const => $ref->getConstant( $const ) ], $ref->getShortName() );

			$table = $input->create( $const )
				->string()
				->match('~^[a-z][a-z0-9$_]*$~i')
				->fetch();

			$classes[ $table ] = $class;
		}

		ksort( $classes );

		return $classes;
	}

	/**
	 * @return array
	 */
	private function getTableParams() : array
	{
		$input = new ExtensionInput( $this, 'table');

		$param['row'] = $input->create('row')
			->optional( Kucbel\Database\Row\ActiveRow::class )
			->string()
			->impl( Nette\Database\Table\IRow::class )
			->fetch();

		$param['scan'] = $input->create('scan')
			->optional()
			->array()
			->string()
			->dir( true )
			->fetch();

		$param['const'] = $input->create('const')
			->optional('TABLE')
			->string()
			->match('~^[A-Z][A-Z0-9_]+$~')
			->fetch();

		$input->match();

		return $param;
	}
}