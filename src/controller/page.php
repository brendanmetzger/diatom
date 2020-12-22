<?php namespace controller;

use Document;

class Page extends \Controller
{
  public function __construct(\Response $response, protected ?string $path = null) {}

  public function GETindex()
  {
    $document = $this->open();
    $document->info['title'] = ucwords($this->name);
    return $document;
  }

}
