<?php namespace controller;

use Document;

class Page extends \Controller
{
  public function __construct(\Response $response, string $name = null, string $path = null) {
    $this->name = $name;
    $this->path = $path;
    $response->endpoint = $response->request->origin;
  }

  public function GETindex()
  {
    $document = $this->open();
    $document->info['title'] = ucwords($this->name);
    return $document;
  }

}
