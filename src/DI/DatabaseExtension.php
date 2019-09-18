<?php

namespace Kucbel\Database\DI;

use Kucbel;
use Kucbel\Entity\DI\EntityExtension;
use Kucbel\Scalar\Input\ExtensionInput;
use Kucbel\Scalar\Validator\ValidatorException;
use Nette;
use Nette\DI\CompilerExtension;
use Nette\DI\Definitions\ServiceDefinition;
use Nette\InvalidStateException;
use Nette\Loaders\RobotLoader;
use Nette\Utils\Strings;
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

		$builder->addDefinition( $this->prefix('repository'))
			->setType( Kucbel\Database\Repository::class )
			->setArguments([ $param['classes'], $param['default'] ]);

		$builder->addDefinition( $this->prefix('transaction'))
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
		$arguments[] = $this->prefix('@repository');

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
		$default = Kucbel\Database\Row\ActiveRow::class;
		$parent = Nette\Database\Table\ActiveRow::class;

		$input = new ExtensionInput( $this );

		$mixed = $input->create('row')
			->optional( $default )
			->string();

		try {
			$param['default'] = $mixed->equal( $parent )->fetch();
		} catch( ValidatorException $ex ) {
			$param['default'] = $mixed->class( $parent )->fetch();
		}

		$folders = $input->create('table.scan')
			->optional()
			->array()
			->string()
			->folder()
			->fetch();

		$const = $input->create('table.const')
			->optional('TABLE')
			->string()
			->match('~^[A-Z][A-Z0-9]*(_+[A-Z0-9]+)*$~')
			->fetch();

		if( $folders ) {
			$param['classes'] = $this->getClasses( $const, ...$folders );
		} else {
			$param['classes'] = [];
		}

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

			$tables = (array) $class->getConstant( $const );

			foreach( $tables as $table ) {
				if( !is_string( $table )) {
					throw new InvalidStateException("Constant '{$type}::{$const}' has invalid format.");
				} elseif( !Strings::match( $table, '~^[a-z][a-z0-9]*(_+[a-z0-9]+)*$~i')) {
					throw new InvalidStateException("Constant '{$type}::{$const}' has invalid table name '{$table}'.");
				}

				$dupe = $classes[ $table ] ?? null;

				if( $dupe ) {
					throw new InvalidStateException("Classes '{$dupe}' and '{$type}' are mapped to the same table '{$table}'.");
				}

				$classes[ $table ] = $class->getName();
			}

		}

		ksort( $classes );

		return $classes;
	}
}
