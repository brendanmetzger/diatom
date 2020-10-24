<?php namespace Controller;

use Document, Element, Render;

class Edit extends \Controller {
  
  public function __construct($authorization = null)
  {
    
    Render::set('before', function($node) {
      [$doc, $context] = $node instanceof Document ? [$node, $node->documentElement] : [$node->ownerDocument, $node];
      
      foreach ($context->find('.//*[not(@data-path or self::script or self::style or contains(., "${")) and text() and not(ancestor::*/text())]') as $node)
        $node->setAttribute('data-path', $node->getNodePath());
      
      if ($path = $doc->info['src'] ?? false)
        $context->setAttribute('data-doc', $path);
    });
    
    Render::set('after', function($context) {
      if ($body = $context->select('//body')) {
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
    // TODO, parse request type so don't have to create document, and this would strip out xmlns perhaps
    $updated  = new Document(preg_replace('/\sxmlns=[\"\'][^\"\']+[\"\'](*ACCEPT)/', '', $this->request->data));
    $filepath = $updated->documentElement->getAttribute('data-doc');
    $original = Document::open($filepath);
    
    foreach ($updated->find('//*[@data-path]') as $node) {
      if ($context = $original->select($node['@data-path'])) {
        $context->parentNode->replaceChild($original->importNode($node, true), $context);
      }
    }
    
    $copy = new Document($original->saveXML());

    foreach($copy->find('//*[@data-path or @data-doc]') as $node);
      array_map([$node, 'removeAttribute'], ['data-doc', 'data-path', 'contenteditable', 'spellcheck']);
    
    if (file_put_contents($filepath, \Parser::check($copy, $original->info['path']['extension'])))
      return $updated;
  }
  
}

