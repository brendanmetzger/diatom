<?php

/**
 * Command | this is the non-http version of the routable interface.
 * can interact with same components of the application, including running the
 * applications own Request/Response calls to gather data (if specified properly).
 */



class Command implements routable
{
  use Registry;
  public $script, $params, $route, $basic, $status = 0, $input, $request, $payload;

  static public function __callStatic($key, $args) {
    return Route::compose(new self([$key, ...$args]));
  }

  public function __construct(array $params = [])
  {
    $this->params = $params;
    $this->request = (object) ['method' => ''];
    $this->route = array_shift($this->params) ?: 'index';
    $this->input  = STDIN;
  }

  public function prompt($message)
  {
    echo $message . ": ";
    return trim(fgets($this->input));
  }

  public function reject(int $reason, $message): Exception {
    if (is_array($message)) {
      $message = "hmm, {$this->route}' not a thing..\n\n >";
      $message .= join("\n > ", array_keys($message));
    }

    return new Exception($message, $reason);
  }


  public function output($instruction = null, ...$data): stringable | string {
    return vsprintf($instruction . "\n", $data);
  }

  public function __toString()
  {
    return $this->payload;
  }

  public function compose($payload, bool $default): self {
    $this->payload = $payload;
    return $this;
  }
}
