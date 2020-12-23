<?php namespace controller;

use Document;

class Page extends \Controller
{
  const PUBLISH = 1;

  public function __construct(\Response $response) {}

  public function GETindex()
  {
    // TODO: should be $this->response->output('file');
    // $document = $this->response->output();
    // $document->info['title'] = ucwords($this->name);
    // return $document;
  }

}
