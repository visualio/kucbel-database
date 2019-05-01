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
		$context = Kucbel\Database\Context::class;
		$builder = $this->getContainerBuilder();

		$builder->addDefinition( $this->prefix('table'))
			->setType( Kucbel\Database\Table\Table::class )
			->setArguments(['database' => "@$context"])
			->setInject()
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
		$builder = $this->getContainerBuilder();

		if( $param['scan'] ) {
			$classes = $this->getTableClasses( $param['scan'], $param['const'] );
		} else {
			$classes = null;
		}

		foreach( $builder->findByType( Nette\Database\Context::class ) as $service ) {
			$factory = $service->getFactory();

			$arguments = $factory->arguments ?? [];
			$arguments['classes'] = $classes;
			$arguments['default'] = null;

			$service->setType( Kucbel\Database\Context::class );
			$service->setFactory( Kucbel\Database\Context::class, $arguments );
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

		$param['scan'] = $input->create('scan')
			->optional()
			->array()
			->string()
			->dir( true )
			->fetch();

		$param['const'] = $input->create('const')
			->optional( $param['scan'] ? 'TABLE' : null )
			->string()
			->match('~^[A-Z][A-Z0-9_]+$~')
			->fetch();

		$input->validate();

		return $param;
	}
}