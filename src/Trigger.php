<?php

namespace Kucbel\Database;

trait Trigger
{
	/**
	 * @var array
	 */
	private $listens = [];

	/**
	 * @param string $event
	 * @param callable $listen
	 * @return $this
	 */
	function subscribe( string $event, callable $listen )
	{
		$this->listens[ $event ][] = $listen;

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