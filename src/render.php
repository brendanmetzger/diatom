<?php

class Render
{
  private $document, $renders = [];
  private function __construct(Document $document)
  {
    $this->document = $document;
    if ($pi = $this->document->select("//processing-instruction('render')"))
      $this->renders = preg_split('/,\s+/', trim($pi->data));
  }
  
  static public function DOM($object) {
    $instance = new self($object instanceof Parser ? $object->DOM : $object);
    
    foreach($instance->renders as $callback)
      call_user_func_array([$instance, strtok($callback, ':')], array_filter(explode('|', strtok($callback))));

    return $instance->document;
  }
  
  private function sections(?Element $context = null, int $level = 2)
  {
    if ($level > 4) return;
    
    $flag     = "h{$level}";
    $context??= $this->document->documentElement;
    $nodeName = $level > 3 ? 'aside' : 'section';
    $sections = $this->document->find('.//'.$flag, $context);

    if ($sections->length > 0) {

      foreach ($sections as $node) {

        $nodeName = strtolower(substr($node->nodeValue, 0, 4)) == 'foot' ? 'footer' : $nodeName;
        $section  = new Element($nodeName);

        $section->appendChild($node->parentNode->replaceChild($section, $node));
        $section->setAttribute('id', preg_replace('/[^a-z]+/', '', strtolower($node)));
        $sibling = $section->nextSibling;
  
        while($sibling && $sibling->nodeName != $flag && $sibling->nodeName != $nodeName) {
          $next = $sibling->nextSibling;
          $section->appendChild($sibling);
          $sibling = $next;
        }
      
        $this->sections($section, $level+1);
      }
    } else {
      $this->sections($context, $level+1);
    }
  }
  
  private function behavior() {
    foreach ($this->document->find('//style') as $node) {
      $text  = $node->replaceChild(new Text("\n    /**/\n    "), $node->firstChild)->nodeValue;
      $cb    = fn($matches) => join(array_map('trim', explode("\n", $matches[0])));
      $cdata = sprintf("*/\n    %s\n    /*", preg_replace_callback('/(\{[^{]+\})/', $cb, preg_replace('/\n\s*\n/', "\n    ", trim($text))));
      $node->insertBefore($this->document->createCDATASection($cdata), $node->firstChild->splitText(7));
    }
  
    // Lazy load all scripts + enforce embed after DOMready 
    foreach ($this->document->find('//script') as $node) {
      $data = $node->getAttribute('src') ?: sprintf("data:application/javascript;base64,%s", base64_encode($node->nodeValue));
      $node("KIT.script('{$data}')")->removeAttribute('src');
    }
  
    // find squashed siblings from markdown (preserveWhitespace )
    foreach ($this->document->find('(//p|//li)/*[preceding-sibling::*]/following-sibling::*') as $node) {
      $node->parentNode->insertBefore(new Text(' '), $node);
    }
  }
  
  private function canonical() {
    // move things that should be in the <head> and specify autolad.js
    if ($head = $this->document->select('/html/head')) {
      $path = sprintf("data:application/javascript;base64,%s", base64_encode(File::load('ux/js/autoload.js')));
      $head->appendChild(new Element('script'))('')->setAttribute('src', $path);
      $this->document->documentElement->setAttribute('xmlns', 'http://www.w3.org/1999/xhtml');
      foreach ($this->document->find('.//style|.//meta|.//link', $head->nextSibling) as $node) $head->appendChild($node);
    } 
  }
}