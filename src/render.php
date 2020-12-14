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

    if ($renders)
      $this->parseInstructions($renders);

    foreach($this->document->find("/processing-instruction('render')") as $pi)
      $this->parseInstructions($pi->data);


  }



  public function parseInstructions($text)
  {
    /*
      TODO this regex could be tuned to use a lookahead of `,(space)` instead of [^,]
    */
    preg_match_all('/([a-z]+)(?:\:([^,]+))?,?/i', $text, $match, PREG_SET_ORDER|PREG_UNMATCHED_AS_NULL);
    foreach ($match as [$full, $method, $args])
      $this->renders[$method] = empty($args) ? [] : explode('|', $args);
  }

  public function headers(?Element $context = null)
  {
    $context ??= $this->document->documentElement;

    $halts = ['section' => false, 'article' => false, 'h1' => false, 'h2' => false];

    foreach ($context->find('.//article/h1') as $node) {
      $header = new Element('header');
      $header->appendChild($node->parentNode->replaceChild($header, $node));
      $header->setAttribute('id', trim(preg_replace('/[^a-z]+/', '-', strtolower($node)), '-'));

      $sibling = $header->nextSibling;

      while($sibling && ($halts[$sibling->nodeName] ?? true)) {
        $next = $sibling->nextSibling;
        $header->appendChild($sibling);
        $sibling = $next;
      }
    }
  }

  private function sections(?Element $context = null, int $level = 2)
  {
    if ($level > 5) {
      $this->asides();
      return;
    }

    $context ??= $this->document->documentElement;
    $flag      = "h{$level}";
    $query     = ".//{$flag}[not(parent::section) or count(parent::section/{$flag}) > 1]";
    $sections  = $this->document->find($query, $context);
    $halts     = [$flag => false, 'section' => false, 'figure' => false, 'article' => false];
    if ($sections->length > 0) {

      foreach ($sections as $node) {
        $section = new Element('section');
        $section->appendChild($node->parentNode->replaceChild($section, $node));
        $section->setAttribute('id', trim(preg_replace('/[^a-z]+/', '-', strtolower($node)), '-'));
        $sibling = $section->nextSibling;

        if ($sibling && $sibling->nodeName == 'hr') {
          $section->setAttribute('aria-label', $node->nodeValue);
          $sibling->remove();
          $sibling = $section->nextSibling;
        }

        while($sibling && ($halts[$sibling->nodeName] ?? true)) {
          $next = $sibling->nextSibling;
          $section->appendChild($sibling);
          $sibling = $next;
        }
      }
    }

    $this->sections($context, $level+1);
  }

  private function asides()
  {
    foreach ($this->document->find('//hr[@title]') as $node) {
      $section = new Element('aside');
      $node->parentNode->replaceChild($section, $node);
      $section->setAttribute('aria-label', $node->getAttribute('title'));
      $section->setAttribute('role', 'note');

      $halts   = ['section' => false, 'hr' => false];
      $sibling = $section->nextSibling;

      while($sibling && ($halts[$sibling->nodeName] ?? true)) {
        $next = $sibling->nextSibling;
        $section->appendChild($sibling);
        $sibling = $next;
      }
    }
  }

  private function behavior() {
    foreach ($this->document->find('//style') as $node) {
      $text  = $node->replaceChild(new Text("\n    /**/\n    "), $node->firstChild)->nodeValue;
      $node->setAttribute('id', md5($text));
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
        $id = $node->parentNode->getAttribute('id') ?: uniqid('DET_');
        $node->setAttribute('role', 'button');
        $node->setAttribute('aria-expanded', 'false');
        $node->setAttribute('aria-controls', $id);
        $node->parentNode->setAttribute('id', $id);
        $node->parentNode->setAttribute('role', 'group');
      }
    }
  }

  public function markdown($exp)
  {
    foreach ($this->document->find("//*[{$exp}]") as $node)
      Parser::markdown($node);
  }

}
