<?php

namespace Mini\Routing;

use Mini\Container\Container;
use Mini\Http\Exception\HttpResponseException;
use Mini\Http\Request;
use Mini\Routing\DependencyResolverTrait;
use Mini\Routing\RouteCompiler;
use Mini\Support\Arr;

use ReflectionFunction;


class Route
{
	use DependencyResolverTrait;

	/**
	 * The URI pattern the route responds to.
	 *
	 * @var string
	 */
	protected $uri;

	/**
	 * Supported HTTP methods.
	 *
	 * @var array
	 */
	private $methods = array();

	/**
	 * The action that is assigned to the route.
	 *
	 * @var mixed
	 */
	protected $action;

	/**
	 * The default values for the route.
	 *
	 * @var array
	 */
	protected $defaults = array();

	/**
	 * The regular expression requirements.
	 *
	 * @var array
	 */
	protected $wheres = array();

	/**
	 * The parameters that will be passed to the route callback.
	 *
	 * @var array
	 */
	protected $parameters = array();

	/**
	 * The parameter names for the route.
	 *
	 * @var array|null
	 */
	protected $parameterNames;

	/**
	 * The regex pattern the route responds to.
	 *
	 * @var string
	 */
	protected $regex;

	/**
	 * The container instance used by the route.
	 *
	 * @var \Mini\Container\Container
	 */
	protected $container;


	/**
	 * Create a new Route instance.
	 *
	 * @param  string|array  $method
	 * @param  string		$uri
	 * @param  array		 $action
	 */
	public function __construct($method, $uri, $action, $wheres = array())
	{
		$methods = array_map('strtoupper', (array) $method);

		if (in_array('GET', $methods) && ! in_array('HEAD', $methods)) {
			array_push($methods, 'HEAD');
		}

		// Properly prefix the URI pattern.
		$uri = '/' .trim(trim(Arr::get($action, 'prefix'), '/') .'/' .trim($uri, '/'), '/');

		//
		$this->uri		= $uri;
		$this->methods	= $methods;
		$this->action	= $action;
		$this->wheres	= $wheres;
	}

	/**
	 * Run the route action and return the response.
	 *
	 * @param  \Mini\Http\Request  $request
	 * @return mixed
	 */
	public function run(Request $request)
	{
		$this->container = $this->container ?: new Container();

		try {
			if (! is_string($this->action['uses'])) {
				return $this->runCallable($request);
			}

			if ($this->customDispatcherIsBound()) {
				return $this->runWithCustomDispatcher($request);
			}

			return $this->runController($request);
		}
		catch (HttpResponseException $e) {
			return $e->getResponse();
		}
	}

	/**
	 * Run the route action and return the response.
	 *
	 * @param  \Mini\Http\Request  $request
	 * @return mixed
	 */
	protected function runCallable(Request $request)
	{
		$callable = $this->action['uses'];

		$parameters = $this->resolveMethodDependencies(
			$this->parameters(), new ReflectionFunction($callable)
		);

		return call_user_func_array($callable, $parameters);
	}

	/**
	 * Run the route action and return the response.
	 *
	 * @param  \Mini\Http\Request  $request
	 * @return mixed
	 *
	 * @throws \Symfony\Component\HttpKernel\Exception\NotFoundHttpException
	 */
	protected function runController(Request $request)
	{
		list($controller, $method) = explode('@', $this->action['uses']);

		$parameters = $this->resolveClassMethodDependencies(
			$this->parameters(), $class, $method
		);

		if (! method_exists($instance = $this->container->make($controller), $method)) {
			throw new NotFoundHttpException();
		}

		return $instance->callAction($method, $parameters);
	}

	/**
	 * Determine if a custom route dispatcher is bound in the container.
	 *
	 * @return bool
	 */
	protected function customDispatcherIsBound()
	{
		return $this->container->bound('framework.route.dispatcher');
	}

	/**
	 * Send the request and route to a custom dispatcher for handling.
	 *
	 * @param  \Mini\Http\Request  $request
	 * @return mixed
	 */
	protected function runWithCustomDispatcher(Request $request)
	{
		list($controller, $method) = explode('@', $this->action['uses']);

		$dispatcher = $this->container->make('framework.route.dispatcher');

		return $dispatcher->dispatch($this, $request, $controller, $method);
	}

	/**
	 * Checks if a Request matches the Route pattern.
	 *
	 * @param \Mini\Http\Request $request The dispatched Request instance
	 * @param bool $includingMethod Wheter or not is matched the HTTP Method
	 * @return bool Match status
	 */
	public function matches(Request $request, $includingMethod = true)
	{
		if ($includingMethod && ! in_array($request->method(), $this->methods)) {
			return false;
		}

		$path = '/' .ltrim($request->path(), '/');

		//
		$pattern = $this->compile();

		if (preg_match($pattern, $path, $matches) === 1) {
			$parameters = $this->matchToParameters($matches);

			$this->parameters = $this->replaceDefaults($parameters);

			return true;
		}

		return false;
	}

	/**
	 * Compile the Route pattern for matching and return it.
	 *
	 * @return string
	 * @throws \LogicException
	 */
	public function compile()
	{
		if (isset($this->regex)) {
			return $this->regex;
		}

		return $this->regex = RouteCompiler::compile($this->uri, $this->wheres);
	}

	/**
	 * Get or set the middlewares attached to the route.
	 *
	 * @param  array|string|null $middleware
	 * @return $this|array
	 */
	public function middleware($middleware = null)
	{
		if (is_null($middleware)) {
			return $this->gatherMiddleware();
		}

		if (is_string($middleware)) {
			$middleware = array($middleware);
		}

		$this->action['middleware'] = array_merge(
			$this->gatherMiddleware(), $middleware
		);

		return $this;
	}

	protected function gatherMiddleware()
	{
		$middleware = Arr::get($this->action, 'middleware', array());

		if (is_string($middleware)) {
			return explode('|', $middleware);
		}

		return $middleware;
	}

	/**
	 * Get a given parameter from the route.
	 *
	 * @param  string  $name
	 * @param  mixed   $default
	 * @return string
	 */
	public function parameter($name, $default = null)
	{
		$parameters = $this->parameters();

		return Arr::get($parameters, $name, $default);
	}

	/**
	 * Get the key / value list of parameters for the route.
	 *
	 * @return array
	 */
	public function parameters()
	{
		return array_map(function($value)
		{
			return is_string($value) ? rawurldecode($value) : $value;

		}, $this->parameters);
	}

	/**
	 * Get all of the parameter names for the route.
	 *
	 * @return array
	 */
	public function parameterNames()
	{
		if (isset($this->parameterNames)) {
			return $this->parameterNames;
		}

		preg_match_all('/\{(.*?)\}/', $this->uri, $matches);

		return $this->parameterNames = array_map(function ($value)
		{
			return trim($value, '?');

		}, $matches[1]);
	}

	/**
	 * Combine a set of parameter matches with the route's keys.
	 *
	 * @param  array  $matches
	 * @return array
	 */
	protected function matchToParameters(array $matches)
	{
		$parameterNames = $this->parameterNames();

		if (count($parameterNames) == 0) {
			return array();
		}

		$parameters = array_intersect_key($matches, array_flip($parameterNames));

		return array_filter($parameters, function($value)
		{
			return is_string($value) && (strlen($value) > 0);
		});
	}

	/**
	 * Replace null parameters with their defaults.
	 *
	 * @param  array  $parameters
	 * @return array
	 */
	protected function replaceDefaults(array $parameters)
	{
		$parameterNames = $this->parameterNames();

		foreach ($this->defaults as $key => $value) {
			if (isset($parameters[$key])) {
				// No need for defaults.
			} else if (in_array($key, $parameterNames) && isset($value)) {
				$parameters[$key] = $value;
			}
		}

		return $parameters;
	}

	/**
	 * Set a default value for the route.
	 *
	 * @param  string  $key
	 * @param  mixed  $value
	 * @return $this
	 */
	public function defaults($key, $value)
	{
		$this->defaults[$key] = $value;

		return $this;
	}

	/**
	 * Set a regular expression requirement on the route.
	 *
	 * @param  array|string  $name
	 * @param  string  $expression
	 * @return $this
	 * @throws \BadMethodCallException
	 */
	public function where($name, $expression = null)
	{
		foreach ($this->parseWhere($name, $expression) as $name => $expression) {
			$this->wheres[$name] = $expression;
		}

		return $this;
	}

	/**
	 * Parse arguments to the where method into an array.
	 *
	 * @param  array|string  $name
	 * @param  string  $expression
	 * @return array
	 */
	protected function parseWhere($name, $expression)
	{
		return is_array($name) ? $name : array($name => $expression);
	}

	/**
	 * Determine if the route only responds to HTTP requests.
	 *
	 * @return bool
	 */
	public function httpOnly()
	{
		return in_array('http', $this->action, true);
	}

	/**
	 * Determine if the route only responds to HTTPS requests.
	 *
	 * @return bool
	 */
	public function httpsOnly()
	{
		return $this->secure();
	}

	/**
	 * Determine if the route only responds to HTTPS requests.
	 *
	 * @return bool
	 */
	public function secure()
	{
		return in_array('https', $this->action, true);
	}

	/**
	 * Get the regular expression requirements on the route.
	 *
	 * @return array
	 */
	public function getWheres()
	{
		return $this->wheres;
	}

	/**
	 * Get the regex for the route.
	 *
	 * @return string
	 */
	public function getRegex()
	{
		return $this->regex;
	}

	/**
	 * @return array
	 */
	public function getMethods()
	{
		return $this->methods;
	}

	/**
	 * Get the URI associated with the route.
	 *
	 * @return string
	 */
	public function getPath()
	{
		return $this->uri;
	}

	/**
	 * Get the uri of the route instance.
	 *
	 * @return string|null
	 */
	public function getUri()
	{
		return $this->uri;
	}

	/**
	 * Get the name of the route instance.
	 *
	 * @return string
	 */
	public function getName()
	{
		return Arr::get($this->action, 'as');
	}

	/**
	 * Get the action name for the route.
	 *
	 * @return string
	 */
	public function getActionName()
	{
		return Arr::get($this->action, 'controller', 'Closure');
	}

	/**
	 * Return the Action array.
	 *
	 * @return array
	 */
	public function getAction()
	{
		return $this->action;
	}

	/**
	 * Set the container instance on the route.
	 *
	 * @param  \Mini\Container\Container  $container
	 * @return $this
	 */
	public function setContainer(Container $container)
	{
		$this->container = $container;

		return $this;
	}

	/**
	 * Dynamically access route parameters.
	 *
	 * @param  string  $key
	 * @return mixed
	 */
	public function __get($key)
	{
		return $this->parameter($key);
	}

}
