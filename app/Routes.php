<?php

//
// General patterns for the route parameters.

$router->pattern('slug', '.*');

//
// The routes definition.

$router->get('/', function()
{
	$view = View::make('Default')
		->shares('title', 'Welcome')
		->with('content', 'Yep! It works.');

	return View::make('Layouts/Default')->with('content', $view);
});

// The Language Changer.
$router->get('language/{language}', array('middleware' => 'referer', function($language)
{
	$languages = Config::get('languages');

	if (in_array($language, array_keys($languages))) {
		Session::set('language', $language);

		// Store also the current Language in a Cookie lasting five years.
		Cookie::queue(PREFIX .'language', $language, 2628000);
	}

	return Redirect::back();

}))->where('language', '([a-z]{2})');

/*
// A Catch-All route.
$router->any('{slug}', function($slug)
{
	$view = View::make('Default')
		->shares('title', 'Catch-All Route')
		->with('content', $slug);

	return View::make('Layouts/Default')->with('content', $view);
});
*/
