<?php

namespace Backend\Providers;

use Mini\Events\Dispatcher;
use Mini\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;


class EventServiceProvider extends ServiceProvider
{
	/**
	 * The event listener mappings for the application.
	 *
	 * @var array
	 */
	protected $listen = array(
		'Backend\Events\SomeEvent' => array(
			'Backend\Listeners\EventListener',
		),
	);


	/**
	 * Register any other events for your application.
	 *
	 * @param  \Mini\Events\Dispatcher  $events
	 * @return void
	 */
	public function boot(Dispatcher $events)
	{
		parent::boot($events);

		//
	}
}
