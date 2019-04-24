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

		$builder->addDefinition( $this->prefix('table'))
			->setType( Kucbel\Database\Table\Table::class )
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
		$param = $this->getParameters();
		$classes = $this->getClasses( $param['scan'], $param['const'] );

		$builder = $this->getContainerBuilder();

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
	private function getClasses( array $scan, string $const ) : array
	{
		$robot = new RobotLoader;
		$robot->addDirectory( $scan );
		$robot->rebuild();

		$classes = [];

		foreach( $robot->getIndexedClasses() as $type => $path ) {
			$class = new ReflectionClass( $type );

			if( !$class->isSubclassOf( Nette\Database\Table\IRow::class ) or !$class->isInstantiable() or !$class->hasConstant( $const ) ) {
				continue;
			}

			$input = new DirectInput([ $const => $class->getConstant( $const ) ], $class->getShortName() );

			$tables = $input->create( $const )
				->optional()
				->array()
				->string()
				->match('~^[a-z][a-z0-9$_]*$~i')
				->fetch();

			if( $tables ) {
				foreach( $tables as $table ) {
					$classes[ $table ] = $type;
				}
			}
		}

		ksort( $classes );

		return $classes;
	}

	/**
	 * @return array
	 */
	private function getParameters() : array
	{
		$input = new ExtensionInput( $this, 'row');

		$param['scan'] = $input->create('scan')
			->array()
			->min( 1 )
			->string()
			->dir( true )
			->fetch();

		$param['const'] = $input->create('const')
			->optional('TABLE')
			->string()
			->match('~^[A-Z][A-Z0-9_]+$~')
			->fetch();

		$input->validate();

		return $param;
	}
}