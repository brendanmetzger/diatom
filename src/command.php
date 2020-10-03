<?php

/**
 * Command | this is the non-http version of the routable interface.
 * can interact with same components of the application, including running the
 * applications own Request/Response calls to gather data (if specified properly).
 */



class Command implements routable
{
  use Registry;
  public $script, $params, $action, $status = 0, $input;
    
  static public function __callStatic($key, $args) {
    return Route::compose(new self([$key, ...$args]));
  }
  
  public function __construct(array $params = [])
  {
    $this->params = $params;
    $this->action = array_shift($this->params) ?: 'index';
    $this->input  = STDIN;
  }
  
  public function prompt($message)
  {
    echo $message . ": ";
    return trim(fgets($this->input));
  }
  
  public function error($info): Exception {
    $routes = join("\n > ", array_keys($info));
    return new Exception("hmm, {$this->action}' not a thing..\n\n > {$routes}\n", 404);
  }
  

  public function __invoke($template) {
    return $template;
  }
  
  public function delegate(Route $route, $payload) {
    return $payload;
  }
}
