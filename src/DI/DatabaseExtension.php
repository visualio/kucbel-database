<?php

namespace Kucbel\Database\DI;

use Kucbel;
use Nette;
use Nette\DI\CompilerExtension;
use Nette\DI\Definitions\ServiceDefinition;
use Nette\InvalidStateException;
use Nette\Loaders\RobotLoader;
use Nette\Schema\Expect;
use Nette\Schema\Schema;
use Nette\Utils\Strings;
use ReflectionClass;

class DatabaseExtension extends CompilerExtension
{
	/**
	 * Config
	 */
	function loadConfiguration()
	{
		if( $this->config->search ) {
			$classes = $this->getClasses();
		} else {
			$classes = [];
		}

		$builder = $this->getContainerBuilder();

		$builder->addDefinition( $this->prefix('repository'))
			->setType( Kucbel\Database\Repository::class )
			->setArguments([ $classes, $this->config->row->default ]);

		$builder->addDefinition( $this->prefix('transaction'))
			->setType( Kucbel\Database\Transaction::class );

		$builder->addFactoryDefinition( $this->prefix('table'))
			->setImplement( Kucbel\Database\Table\TableFactory::class )
			->addTag('nette.inject');
	}

	/**
	 * Compile
	 */
	function beforeCompile()
	{
		$arguments = [];
		$arguments[] = $this->prefix('@repository');

		$builder = $this->getContainerBuilder();

		$services = $builder->findByType( Nette\Database\Explorer::class );

		foreach( $services as $service ) {
			if( !$service instanceof ServiceDefinition ) {
				continue;
			}

			$factory = $service->getFactory();

			$service->setType( Kucbel\Database\Explorer::class );
			$service->setFactory( Kucbel\Database\Explorer::class, array_merge( $arguments, $factory->arguments ));
		}
	}

	/**
	 * @return Schema
	 */
	function getConfigSchema() : Schema
	{
		$parent = Nette\Database\Table\ActiveRow::class;
		$custom = Kucbel\Database\Row\ActiveRow::class;

		$test = [];
		$test['row'] = function( string $value ) use( $parent ) {
			return class_exists( $value ) and is_a( $value, $parent, true );
		};

		$cast = [];
		$cast['array'] = function( $value ) {
			return is_scalar( $value ) ? [ $value ] : $value;
		};

		return Expect::structure([
			'row' => Expect::structure([
				'default'	=> Expect::string( $custom )->assert( $test['row'], "Row must extend {$parent} class."),
				'const'		=> Expect::string('TABLE')->pattern('[A-Z_]+'),
			]),

			'search' => Expect::arrayOf(
				Expect::string()->assert('is_dir', "Search folder must exist."),
			)->before( $cast['array'] ),
		]);
	}

	/**
	 * @return array
	 * @throws
	 */
	private function getClasses() : array
	{
		if( !$this->config->search ) {
			throw new InvalidStateException("No folders to search.");
		}

		$const = $this->config->row->const;

		$robot = new RobotLoader;
		$robot->addDirectory( ...$this->config->search );
		$robot->reportParseErrors( false );
		$robot->rebuild();

		$parent = Nette\Database\Table\ActiveRow::class;
		$records = [];

		foreach( $robot->getIndexedClasses() as $type => $file ) {
			$class = new ReflectionClass( $type );

			if( !$class->isSubclassOf( $parent ) or !$class->hasConstant( $const ) or !$class->isInstantiable() ) {
				continue;
			}

			$tables = $class->getConstant( $const );

			if( is_string( $tables )) {
				$tables = [ $tables ];
			} elseif( !is_array( $tables )) {
				throw new InvalidStateException("Constant {$type}::{$const} has invalid format.");
			}

			foreach( $tables as $table ) {
				if( !is_string( $table )) {
					throw new InvalidStateException("Constant {$type}::{$const} has invalid format.");
				} elseif( !Strings::match( $table, '~^[a-z][a-z0-9]*(_+[a-z0-9]+)*$~i')) {
					throw new InvalidStateException("Constant {$type}::{$const} has invalid table name \"{$table}\".");
				}

				$dupe = $records[ $table ] ?? null;

				if( $dupe ) {
					throw new InvalidStateException("Classes {$dupe} and {$type} are mapped to the same table \"{$table}\".");
				}

				$records[ $table ] = $class->getName();
			}
		}

		ksort( $records );

		return $records;
	}
}
