<?php namespace Controller;

use Document, Element, Render;

class Edit extends \Controller {
  
  public function __construct($authorization = null)
  {
    Render::set('open', function($DOM) {
      foreach ($DOM->find('//*[not(self::script or self::style or contains(., "${")) and text() and not(ancestor::*/text())]') as $node)
          $node->setAttribute('data-path', $node->getNodePath());
    });
    
    Render::set('before', function($DOM) {
      if ($path = $DOM->info['src'] ?? false)
        $DOM->documentElement->setAttribute('data-doc', $path);
    });
    
    Render::set('after', function($DOM) {
      if ($body = $DOM->select('//body')) {
        $body->setAttribute('data-root', realpath('.'));
        $body->appendChild(new Element('script'))->setAttribute('src', 'ux/js/edit.js');
      }
    });
  }
  
  public function initialize($response)
  {

  }
  
  public function update()
  {
    // TODO, parse request type so don't have to create document
    $updated  = new Document($this->request->data);
    $filepath = $updated->documentElement->getAttribute('data-doc');
    $original = Document::open($filepath);
    
    foreach ($updated->find('//*[@data-path]') as $node) {
      if ($context = $original->select($node['@data-path'])) {
        $context->parentNode->replaceChild($original->importNode($node, true), $context);
      }
    }
    
    $copy = new Document($original->saveXML());

    foreach($copy->find('//*[@data-path or @data-doc]') as $node);
      array_map([$node, 'removeAttribute'], ['data-doc', 'data-path','contenteditable', 'spellcheck']);
    
    // FIXME this should not default to md type
    file_put_contents($filepath, \Parser::check($copy, 'md'));
    
    return $original;
  }
  
}

