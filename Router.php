<?php

namespace LiquidMS;

class Router {

  private static $routes = [];
  private static $notFoundFunc = null;
  private static $invalidFunc = null;

  public static function get( string $pattern, \callback|\closure $function){
	 array_push(self::$routes, [
		  'pattern' => $pattern,
		  'function' => $function,
		  'method' => "GET",
	 ]);
  }

  public static function post( string $pattern, \callback|\closure $function){
	 array_push(self::$routes, [
		  'pattern' => $pattern,
		  'function' => $function,
		  'method' => "POST",
	 ]);
  }

  public static function getRoutes(){
	 return self::$routes;
  }

  public static function notFound(\callback|\closure $function) {
	 self::$notFoundFunc = $function;
  }

  public static function invalidPage(\callback|\closure $function) {
	 self::$invalidFunc = $function;
  }

  public static function run($basepath = '', $case_matters = false, $trailing_slash_matters = false, $multimatch = false) {

	 // The basepath never needs a trailing slash
	 // Because the trailing slash will be added using the route expressions
	 $basepath = rtrim($basepath, '/');

	 // Parse current URL
	 $parsed_url = parse_url($_SERVER['REQUEST_URI']);

	 $path = '/';

	 // If there is a path available
	 if (isset($parsed_url['path'])) {
		// If the trailing slash matters
		if ($trailing_slash_matters) {
		  $path = $parsed_url['path'];
		} else {
		  // If the path is not equal to the base path (including a trailing slash)
		  if($basepath.'/'!=$parsed_url['path']) {
			 // Cut the trailing slash away because it does not matters
			 $path = rtrim($parsed_url['path'], '/');
		  } else {
			 $path = $parsed_url['path'];
		  }
		}
	 }

	 $path = urldecode($path);

	 // Get current request method
	 $method = $_SERVER['REQUEST_METHOD'];

	 $pathmatch = false;

	 $routematch = false;

	 foreach (self::$routes as $route) {

		// If the method matches check the path

		// Add basepath to matching string
		if ($basepath != '' && $basepath != '/') {
		  $route['pattern'] = '('.$basepath.')'.$route['pattern'];
		}

		// Add 'find string start' automatically
		$route['pattern'] = '^'.$route['pattern'];

		// Add 'find string end' automatically
		$route['pattern'] = $route['pattern'].'$';

		// Check path match
		if (preg_match('#'.$route['pattern'].'#'.($case_matters ? '' : 'i').'u', $path, $matches)) {
		  $pathmatch = true;

		  // Cast allowed method to array if it's not one already, then run through all methods
		  foreach ((array)$route['method'] as $allowedMethod) {
			 // Check method match
			 if (strtolower($method) == strtolower($allowedMethod)) {
				array_shift($matches); // Always remove first element. This contains the whole string

				if ($basepath != '' && $basepath != '/') {
				  array_shift($matches); // Remove basepath
				}

				if($return_value = call_user_func_array($route['function'], $matches)) {
				  echo $return_value;
				}

				$routematch = true;

				// Do not check other routes
				break;
			 }
		  }
		}

		// Break the loop if the first found route is a match
		if($routematch&&!$multimatch) { break; }

	 }

	 // No matching route found?
	 if (!$routematch) {
		// Matching path found?
		if ($pathmatch) {
		  if (self::$methodNotAllowed) {
			 // Call registered 400 function
			 echo call_user_func_array(self::$methodNotAllowed, [$path, $method]);
		  }
		} else {
		  if (self::$notFoundFunc) {
			 // Call registered 404 function
			 echo call_user_func_array(self::$notFoundFunc, [$path]);
		  }
		}

	 }
  }

}
