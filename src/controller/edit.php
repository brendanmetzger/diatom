<?php namespace Controller;

use Document, Element, Render;

class Edit extends \Controller {
  
  public function __construct($authorization = null)
  {
    Render::set('before', function($DOM) {
      $DOM->documentElement->setAttribute('data-doc', base64_encode(realpath($DOM->info['src'])));    
      foreach ($DOM->find('//text()[not(../../text()[normalize-space(.)=""])]/..') as $node) {
        if (strpos('script style', $node->nodeName) === false) {
          $node->setAttribute('data-path', $node->getNodePath());
        }
      }
    });
  }
  
  public function initialize($response)
  {
    # code...
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
    // could loop through each data-path and replace 
    // $original->select('//h1')(rand(0, 1000));

    return $original;


    // ensure script and style are cdata'd to avoid getting htmlentites replaced
    foreach ($DOM->find('//script|//style') as $node) $node($DOM->createCDATAsection($node));
  
    $node = $DOM->select($exp);  

    if ($crud == 'create') {
      $node = $node->parentNode->insertBefore(new Element($node->nodeName), $node->nextSibling);
    }
  
    $fragment = $DOM->createDocumentFragment();
    $fragment->appendXML($this->request->data);

    $node($fragment);

    // consider returning re-rendered template of request url so other fields are updated
    return "Saved {$path}: " .($DOM->save($path) ?  "Ok" : 'Fail');
    
  }
  
}

