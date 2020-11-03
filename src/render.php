<?php

class Render
{
  static private $queue = [
    'before' => [],
    'after'  => [],
  ];
  
  static public function set(string $when, $object) {
    if ($object instanceof DOMNode)
      foreach (self::$queue[$when] as $callback)
        call_user_func($callback, $object);
    else if (is_callable($object))
      self::$queue[$when][] = $object;
  }
  
  static public function transform(Document $DOM, ?string $renders = null) {
    $instance = new self($DOM, $renders);
    
    // run 'after' renders
    foreach (self::$queue['after'] as $callback)
      call_user_func($callback, $instance->document);
    
    foreach($instance->renders as $callback => $args)
      call_user_func_array([$instance, $callback], $args);
    

    return $instance->document;
  }
  
  
  private $document, $renders = [];
  private function __construct(Document $document, ?string $renders = null)
  {
    $this->document = $document;
    
    foreach($this->document->find("/processing-instruction('render')") as $pi)
      $this->parseInstructions($pi->data);
    
    if ($renders)
      $this->parseInstructions($renders);

  }
  
  
  
  public function parseInstructions($text)
  {
    preg_match_all('/([a-z]+)(?:\:([^\s]+))?,?/i', $text, $match, PREG_SET_ORDER|PREG_UNMATCHED_AS_NULL);
    foreach ($match as [$full, $method, $args])
      $this->renders[$method] = empty($args) ? [] : explode('|', $args);
  }
  
  
  private function sections(?Element $context = null, int $level = 2)
  {

    if ($level > 5) return;
    
    $context ??= $this->document->documentElement;
    $flag      = "h{$level}";
    $name      = $level > 4 ? 'aside' : 'section';
    $query     = ".//{$flag}[not(parent::{$name}) or count(parent::{$name}/{$flag}) > 1]";
    $sections  = $this->document->find($query, $context);
    
    if ($sections->length > 0) {

      foreach ($sections as $node) {
        $section = new Element($name);
        $section->appendChild($node->parentNode->replaceChild($section, $node));
        $section->setAttribute('id', preg_replace('/[^a-z]+/', '', strtolower($node)));
        $sibling = $section->nextSibling;
  
        while($sibling && $sibling->nodeName != $flag && $sibling->nodeName != $name) {
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
    foreach ($this->document->find('(//p|//li)/*[preceding-sibling::*[not(text())]]') as $node) {
      $node->parentNode->insertBefore(new Text(' '), $node);
    }
  }
  
  private function canonical() {
    // move things that should be in the <head> and specify autolad.js
    if ($head = $this->document->select('/html/head')) {
      $path = sprintf("data:application/javascript;base64,%s", base64_encode(file_get_contents('ux/js/autoload.js')));
      $head->insertBefore(new Element('script'), $head->firstChild)('')->setAttribute('src', $path);
      $this->document->documentElement->setAttribute('xmlns', 'http://www.w3.org/1999/xhtml');
      foreach ($this->document->find('.//style|.//meta|.//link', $head->nextSibling) as $node) $head->appendChild($node);
      foreach ($this->document->find('.//title', $head->nextSibling) as $node) $head->replaceChild($node, $head->select('title'));
      foreach ($this->document->find('.//summary[not(@role)]') as $node) {
        $id = uniqid('DET_');
        $node->setAttribute('role', 'button');
        $node->setAttribute('aria-expanded', 'false');
        $node->setAttribute('aria-controls', $id);
        $node->parentNode->setAttribute('id', $id);
        $node->parentNode->setAttribute('role', 'group');
      }
    } 
  }
  
  private function example($color = "red")
  {
    foreach ($this->document->find('//body//*') as $node) {
      $node->setAttribute('style', 'background-color: '. $color);
    }
  }
  
  public function markdown($exp)
  {
    foreach ($this->document->find("//*[{$exp}]") as $node)
      Parser::markdown($node);
  }
  
}