<?php 

// this is only for local development as a way to make it easier to edit template content.
// look for all nearly terminal nodes; ie., contain text, but whose parents do not contain text; make editable

Document::on('open', function (Document $DOM) {
  if ($DOM->info['path']['extension'] !== 'html') return;
  $DOM->documentElement->setAttribute('data-doc', base64_encode(realpath($DOM->info['src'])));    
  
  foreach ($DOM->find('//text()[not(../../text()[normalize-space(.)])]/..') as $node) {
    $node->setAttribute('data-id', base64_encode($node->getNodePath()));
    $node->setAttribute('data-line', $node->getLineNo());
  }
  if (($DOM->documentElement->nodeName == 'html')) {
    $DOM->documentElement->appendChild(new Element('script'))->setAttribute('src', 'ux/edit.js');
  }
});


# Update is a shortcut for quick edits to templates.

Route::update(function(string $info, $crud = 'update') {

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
});
