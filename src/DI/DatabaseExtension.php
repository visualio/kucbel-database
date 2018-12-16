<?php

namespace Kucbel\Database\DI;

use Kucbel\Database;
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
	 * Compile
	 *
	 * @throws ReflectionException
	 */
	function beforeCompile()
	{
		$param = $this->getParameters();
		$classes = $this->getClasses( $param['scan'], $param['const'] );

		$builder = $this->getContainerBuilder();

		foreach( $builder->findByType( Nette\Database\Context::class ) as $context ) {
			$previous = $context->getFactory();

			$arguments = $previous ? $previous->arguments : [];
			$arguments[4] = $classes;
			$arguments[5] = null;

			$context->setFactory( Database\Context::class, $arguments );
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

		$tables = [];

		foreach( $robot->getIndexedClasses() as $type => $path ) {
			$class = new ReflectionClass( $type );

			if( !$class->isSubclassOf( Nette\Database\Table\IRow::class ) or !$class->isInstantiable() or !$class->hasConstant( $const ) ) {
				continue;
			}

			$input = new DirectInput([ $const => $class->getConstant( $const ) ], $class->getShortName() );

			$list = $input->create( $const )
				->optional()
				->array()
				->string()
				->match('~^[a-z][a-z0-9$_]*$~i')
				->fetch();

			if( $list ) {
				foreach( $list as $table ) {
					$tables[ $table ] = $type;
				}
			}
		}

		ksort( $tables );

		return $tables;
	}

	/**
	 * @return array
	 */
	private function getParameters() : array
	{
		$input = new ExtensionInput( $this );

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