<?php

namespace Kucbel\Database;

trait Trigger
{
	/**
	 * @var array
	 */
	private $listens;

	/**
	 * @param array | string $event
	 * @param callable $method
	 * @return $this
	 */
	function subscribe( string $event, callable $method )
	{
		$this->listens[ $event ][] = $method;

		return $this;
	}

	/**
	 * @param string $event
	 * @param mixed ...$values
	 */
	protected function dispatch( string $event, &...$values ) : void
	{
		if( isset( $this->listens[ $event ] )) {
			foreach( $this->listens[ $event ] as $listen ) {
				$listen( ...$values );
			}
		}
	}
}