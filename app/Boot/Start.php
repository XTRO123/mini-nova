<?php

use Mini\Config\EnvironmentVariables;
use Mini\Foundation\Application;

//--------------------------------------------------------------------------
// Use Internally The UTF-8 Encoding
//--------------------------------------------------------------------------

mb_internal_encoding('UTF-8');

//--------------------------------------------------------------------------
// Setup The Application Version
//--------------------------------------------------------------------------

define('VERSION', trim(file_get_contents(BASEPATH .'VERSION.txt')));

//--------------------------------------------------------------------------
// Load The Global Configuration
//--------------------------------------------------------------------------

require APPPATH .'Config.php';

//--------------------------------------------------------------------------
// Create New Application
//--------------------------------------------------------------------------

$app = new Application();

//--------------------------------------------------------------------------
// Bind Paths
//--------------------------------------------------------------------------

$app->bindInstallPaths(array(
	'base'		=> BASEPATH,
	'app'		=> APPPATH,
	'public'	=> WEBPATH,
	'storage'	=> STORAGE_PATH,
));

//--------------------------------------------------------------------------
// Bind Important Interfaces
//--------------------------------------------------------------------------

$app->singleton(
	'Mini\Http\Contracts\KernelInterface',
	'App\Http\Kernel'
);

$app->singleton(
	'Nova\Console\Contracts\KernelInterface',
	'App\Console\Kernel'
);

$app->singleton(
	'Mini\Foundation\Contracts\ExceptionHandlerInterface',
	'App\Exceptions\Handler'
);

//--------------------------------------------------------------------------
// Detect The Application Environment
//--------------------------------------------------------------------------

$env = $app->detectEnvironment(array(
	'local' => array('darkstar'),
));

//--------------------------------------------------------------------------
// Check For The Test Environment
//--------------------------------------------------------------------------

if (isset($unitTesting)) {
	$app['env'] = $env = $testEnvironment;
}

//--------------------------------------------------------------------------
// Register The Environment Variables.
//--------------------------------------------------------------------------

with($loader = new EnvironmentVariables($app))->load($env);

//--------------------------------------------------------------------------
// Register Booted Start Files
//--------------------------------------------------------------------------

$app->booted(function () use ($app, $env)
{

//--------------------------------------------------------------------------
// Load The Application Start Script
//--------------------------------------------------------------------------

$path = $app['path'] .DS .'Boot' .DS .'Global.php';

if (is_readable($path)) require $path;

//--------------------------------------------------------------------------
// Load The Environment Start Script
//--------------------------------------------------------------------------

$path = $app['path'] .DS .'Boot' .DS .'Environment' .DS .ucfirst($env) .'.php';

if (is_readable($path)) require $path;

//--------------------------------------------------------------------------
// Load The Boootstrap Script
//--------------------------------------------------------------------------

$path = $app['path'] .DS .'Bootstrap.php';

if (is_readable($path)) require $path;

});

//--------------------------------------------------------------------------
// Return The Application
//--------------------------------------------------------------------------

return $app;
