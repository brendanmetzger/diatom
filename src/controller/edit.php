<?php namespace Controller;


class Edit extends \Controller {
  
  public function __construct()
  {
    Render::set('before', function($DOM) {
      $DOM->documentElement->setAttribute('data-doc', base64_encode(realpath($DOM->info['src'])));    
      foreach ($DOM->find('//text()[not(../../text()[normalize-space(.)])]/..') as $node)
        $node->setAttribute('data-path', $node->getNodePath());
    });
    
  }
  
  public function update(string $info, $crud = 'update')
  {
    [$path, $exp] = array_map('base64_decode', explode('-', $info));
  
    $DOM = new Document(File::load($path));

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

