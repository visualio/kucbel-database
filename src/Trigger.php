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
	 */
	function subscribe( string $event, callable $listen ) : void
	{
		$this->listens[ $event ][] = $listen;
	}

	/**
	 * @param object $service
	 * @param array $events
	 */
	function subscribeMany( object $service, array $events ) : void
	{
		foreach( $events as $event => $method ) {
			$this->listens[ $event ][] = [ $service, $method ];
		}
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