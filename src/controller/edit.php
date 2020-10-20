<?php namespace Controller;

use Document, Element, Render;

class Edit extends \Controller {
  
  public function __construct($authorization = null)
  {
    Render::set('before', function($DOM) {
      $DOM->documentElement->setAttribute('data-doc', base64_encode(realpath($DOM->info['src'])));
      foreach ($DOM->find('//*[text() and not(ancestor::*/text())]') as $node) {
        if (strpos('script style', $node->nodeName) === false) {
          $node->setAttribute('data-path', $node->getNodePath());
        }
      }
    });
  }
  
  public function initialize($response)
  {

  }
  
  public function update()
  {
    $updated  = new Document($this->request->data);
    $filepath = base64_decode($updated->documentElement->getAttribute('data-doc'));
    $original = Document::open($filepath);
    
    foreach ($updated->find('//*[@data-path]') as $node) {
      if ($context = $original->select($node['@data-path'])) {
        $node->removeAttribute('data-path');
        $context->parentNode->replaceChild($original->importNode($node, true), $context);
      }
    }
    
    file_put_contents($filepath, \Parser::check(new Document($original->saveXML()), 'md'));

    return $original;
  }
  
}

