<?php namespace controller;

use Document;

class Page extends \Controller
{
  public function __construct(\Response $response, string $name = null, string $path = null) {
    $this->name = $name;
    $this->path = $path;

    $response->render   = 'behavior, canonical';
    $response->endpoint = $response->uri;
    // Select the appropriate page navigation if possible.
    if ($navigable = Document::open('structure/nav.md')->select("//a[@href='/{$this->name}']/ancestor::li")) {
      $navigable->setAttribute('class', 'active');
    }
  }


  public function GETindex()
  {
    $document = $this->open();
    $document->info['title'] = ucwords($this->name);
    return $document;
  }

  // generator function to set configure Controller instances
  static public function gather($glob)
  {
    $trim = strlen($glob);
    foreach (glob($glob.'*', GLOB_ONLYDIR) as $path) {
      $name = substr($path, $trim);
      $ctrl = sprintf('controller\%s', is_file("../src/controller/{$name}.php") ? $name : 'Page');
      yield $name => new \Proxy($ctrl, [$name, $path]);
    }
  }
}
