<?php

/**
 * Command | this is the non-http version of the router interface.
 * can interact with same components of the application, including running the
 * applications own Request/Response calls to gather data (if specified properly).
 */

class Command implements Router
{
  use Registry;
  public $script, $params, $action, $status = 0, $input;
    
  static public function __callStatic($key, $args) {
    return Route::compose(new self([$key, ...$args]));
  }
  
  public function __construct(array $params = [])
  {
    $this->params = $params;
    $this->action = array_shift($this->params) ?: 'help';
    $this->input  = STDIN;
  }
  
  public function prompt($message)
  {
    echo $message . ": ";
    return trim(fgets($this->input));
  }
    
  public function __invoke(array $routes): Controller {
    return new Class($routes) extends Controller {
      public function __construct($routes) {
        $this->routes = $routes;
      }
      
      public function index() {
        return "Run 'bin/task help *name*' to see signatures\nCommands: \n" 
               . print_r(array_keys($this->routes), true);
      }
      
      public function __call($name, $args) {
        $object = $this->routes[$name];
        if ($object instanceof Controller) {
          $in = new ReflectionObject($object);
          $object = [];
          foreach ($in->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
            if($method->getDeclaringClass()->name == $in->name && $method->name[0] != '_') {
              $object[$method->name] = array_map(fn($param) => (string)$param, $method->getParameters());
            }
          }
        }
        return print_r($object, true);
      }
    };
  }
  
  public function delegate($config) {
    return $config;
  }
}
