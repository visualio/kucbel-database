<?php

namespace Kucbel\Database\DI;

use Kucbel;
use Kucbel\Entity\DI\EntityExtension;
use Kucbel\Scalar\Input\ExtensionInput;
use Kucbel\Scalar\Input\MixedInput;
use Nette;
use Nette\DI\CompilerExtension;
use Nette\DI\Definitions\ServiceDefinition;
use Nette\InvalidStateException;
use Nette\Loaders\RobotLoader;
use ReflectionClass;

class DatabaseExtension extends CompilerExtension
{
	/**
	 * Config
	 */
	function loadConfiguration()
	{
		$param = $this->getParameters();
		$builder = $this->getContainerBuilder();

		$builder->addDefinition( $this->prefix('repo'))
			->setType( Kucbel\Database\Repository::class )
			->setArguments([ $param['classes'], $param['default'] ]);

		$builder->addDefinition( $this->prefix('trans'))
			->setType( Kucbel\Database\Utils\Transaction::class );

		$builder->addDefinition( $this->prefix('table.factory'))
			->setType( Kucbel\Database\Table\TableFactory::class );

		if( $this->compiler->getExtensions( EntityExtension::class )) {
			$builder->addDefinition( $this->prefix('table'))
				->setType( Kucbel\Database\Table\Table::class )
				->addTag('entity')
				->addTag('nette.inject');
		}
	}

	/**
	 * Compile
	 */
	function beforeCompile()
	{
		$arguments[] = $this->prefix('@repo');

		$builder = $this->getContainerBuilder();

		foreach( $builder->findByType( Nette\Database\Context::class ) as $service ) {
			if( !$service instanceof ServiceDefinition ) {
				continue;
			}

			$factory = $service->getFactory();

			$service->setType( Kucbel\Database\Context::class );
			$service->setFactory( Kucbel\Database\Context::class, array_merge( $arguments, $factory->arguments ));
		}
	}

	/**
	 * @return array
	 */
	private function getParameters() : array
	{
		$input = new ExtensionInput( $this, 'table');

		$folders = $input->create('scan')
			->optional()
			->array()
			->string()
			->dir( true )
			->fetch();

		$const = $input->create('const')
			->optional('TABLE')
			->string()
			->match('~^[A-Z][A-Z0-9_]+$~')
			->fetch();

		if( $folders ) {
			$param['classes'] = $this->getClasses( $const, ...$folders );
		} else {
			$param['classes'] = [];
		}

		$input = $input->section('row');

		$param['default'] = $input->create('default')
			->optional( Kucbel\Database\Row\ActiveRow::class )
			->string()
			->impl( Nette\Database\Table\ActiveRow::class )
			->fetch();

		$input->match();

		return $param;
	}

	/**
	 * @param string $const
	 * @param string ...$folders
	 * @return array
	 * @throws
	 */
	private function getClasses( string $const, string ...$folders ) : array
	{
		$robot = new RobotLoader;
		$robot->addDirectory( ...$folders );
		$robot->reportParseErrors( false );
		$robot->rebuild();

		$parent = Nette\Database\Table\ActiveRow::class;
		$classes = [];

		foreach( $robot->getIndexedClasses() as $type => $file ) {
			$class = new ReflectionClass( $type );

			if( !$class->isSubclassOf( $parent ) or !$class->hasConstant( $const ) or !$class->isInstantiable() ) {
				continue;
			}

			$input = new MixedInput([ $const => $class->getConstant( $const ) ], $class->getShortName() );

			$tables = $input->create( $const )
				->array()
				->count( 1, null )
				->string()
				->match('~^[a-z][a-z0-9_]*$~i')
				->fetch();

			foreach( $tables as $table ) {
				$exist = $classes[ $table ] ?? null;

				if( $exist ) {
					throw new InvalidStateException("Duplicate table '$table' mapped in rows '$exist' and '$class'.");
				}

				$classes[ $table ] = $class->getName();
			}

		}

		ksort( $classes );

		return $classes;
	}
}
