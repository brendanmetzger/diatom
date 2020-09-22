<?php

class Parser {

  public $DOM, $context = null;
      
  public function __construct(string $input, string $xml = '<article/>', string $xpath = '/*') {
    $this->DOM     = new Document($xml);
    $this->context = $this->DOM->select($xpath) ?? $this->DOM->documentElement;
    $path  = substr($input, -3) == '.md' && strpos($input, "\n") === false;
    $input = $path ? new SplFileObject($input) : array_map(fn($line) => $line . "\n", explode("\n", $input));
    foreach ($this->scan($input) as $block) $block->process($this->context);
  }
  
  static public function load(File $file) {
    $ext = ['css' => '<style/>', 'js' => '<script/>', 'txt' => '<pre/>'];
    
    if ($file->type == 'md')
      return Render::DOM(new self($file->uri));
    
    else if (isset($ext[$file->type])) {
      $DOM = new Document($ext[$file->type]);
      $DOM->documentElement->appendChild(new Text($file->body));
      return $DOM;
    }
    throw new Error("{$file->type} Not Supported", 500);
  }
  

  private function scan($iterator)
  {
    $block = new Block;

    foreach ($iterator as $line) {
      $result = $block->capture($line);
      if ($block === $result) continue;
      yield $block;
      $block = $result;
    }
    yield $block;
  }
    
  public function __toString() {
    return $this->DOM->saveXML();
  }
  
  static public function addCallback(callable $callback) {
    self::$callbacks[] = $callback;
  }
}

class Token {
  
  const BLOCK = [
    'name' => [ 'ol'    , 'ul' ,  'h%d' ,'CDATA', 'blockquote',  'hr'  ,'comment', 'pi', 'p' ],
    'rgxp' => ['\d+\. ?', '- ' ,'#{1,6}','`{3}' ,   '> ?'     , '-{3,}',  '\/\/' , '\?', '\S'],
  ];
  
  const INLINE = [
    '~~' => 's',
    '**' => 'strong',
    '__' => 'em',
    '``' => 'time',
    '`'  => 'code',
    '^^' => 'abbr',
    '|'  => 'mark',
    '"'  => 'q',
    '*'  => 'i',
  ];
  
  public $flag, $trim, $depth, $text, $name, $rgxp, $value, $context = false, $element = null;
  
  function __construct($data) {
    foreach ($data as $prop => $value) $this->{$prop} = $value;
    $this->value =  trim(substr($this->text, $this->name == 'p' ? 0 : $this->trim * $this->depth));
    if ($this->context = in_array($this->name, ['CDATA','ol','ul','blockquote'])) {
      if ($this->name == 'CDATA')
        $this->element = trim(substr($this->text, 3)) ?: 'pre';
      else 
        $this->element = $this->name == 'blockquote' ? 'p' : 'li';
    }
  }
}


class Block {
  private $token = [], $trap = false, $cursor = null;
  private static $rgxp;

  public function __construct(?Token $token = null) {
    self::$rgxp ??= sprintf("/^\s*(?:%s)/Ai", implode('|', array_map(fn($x) => "($x)", Token::BLOCK['rgxp'])));
    if ($token) $this->push($token);
  }
  
  public function parse($text)
  {
    if (preg_match(self::$rgxp, $text, $list, PREG_OFFSET_CAPTURE) < 1) return false;

    [$symbol, $offset] = array_pop($list); // last match contains match & offset: [string $symbol, int offset]
    $trim = strlen($symbol);

    return new Token ([
      'name'  => sprintf(Token::BLOCK['name'][count($list) - 1], $trim),
      'flag'  => $symbol,
      'trim'  => $trim,
      'depth' => floor($offset / 2) + 1,
      'text'  => $text,
    ]);
  }
  
  public function push(Token $token): Block {
    $this->token[] = $this->cursor = $token;
    return $this;
  }
  
  public function capture(string $line)
  {
    if (! $token = $this->parse($line)) {
      if ($this->trap) $this->push(new Token(['text' => $line]));
      return $this;
    }
      
    
    if ($token->context || $this->trap)
      return $this->evaluate($token);

    return empty($this->token) ? $this->push($token) : new self($token);
  }
  
  public function evaluate(Token $token)
  {
    
    if ($this->trap || $token->name === 'CDATA') {
      if ($this->trap === false && $this->trap = $token->flag) {
        $token->text = "\n";
        return $this->push($token);
      }
        

      elseif ($this->trap == trim($token->text))
        return new self;

      $token->name = 'CDATA';

      return $this->push($token);
    }
    
    if ($this->cursor && $token->name != $this->cursor->name && $token->depth == $this->cursor->depth)
      return new self($token);
        
    return $this->push($token);
  }
  
  
  public function process(DOMElement $context): void
  {
    foreach($this->token as $token) {
      
      if ($context instanceof DOMText) {
        $context->appendData($token->text);
        continue;
      }
      $context = $this->append($context, $token);
    }
  }
  
  private function append($context, $token)
  {
    if ($token->name == 'comment') {
      $context->appendChild(new DOMComment($token->value));
      return $context;
    }

    if ($token->name == 'pi' && $doc = $context->ownerDocument) {
      $doc->insertBefore(new DOMProcessingInstruction(...explode(' ', $token->value, 2)), $doc->firstChild);
      return $context;
    }
      
    
    # TODO check the depth and decide if it's time to move up or down (might be a good fit for kernel Element enhancments)
    if ($token->element && $token->name != 'CDATA' && $context->nodeName != $token->name)
     $context = $context->appendChild(new DOMElement($token->name));
    
    $element = $context->appendChild(new DOMElement($token->element ?? $token->name));
    
    if ($token->name === 'hr')
      return $context;
    
    if ($token->name === 'CDATA')
      return $element->appendChild(new DOMText($token->text));
    
    $element->nodeValue = preg_replace(['/(?<=\s)\'/u', '/(?<=\S)\'/u', '/\s?--\s?/u'], ['‘', '’', '—'], htmlspecialchars($token->value, ENT_XHTML, 'UTF-8', false));
    // Inline::format($element);
    (new Inline($element))->parse();

    return $context;
  }
}

/*
  TODO consider Inline extends DOMText  
  ... ie. $parent->appendChild(new Inline($text));
  and can do things like $this->splitText($offset) within Inline class
*/

# note: (?<=<)(?:https?:\/)?\/(.+?)(?=\/).*(?=>) can find links with the <> encapsulation.
#       I do not use in my projects and encurage the more the explicit format.

class Inline {
  private static $rgxp = null;
  
  private $DOM, $node;
  
  public function __construct(DOMElement $node)
  {
    self::$rgxp ??= [
      'pair' => sprintf('/(%s)((?:(?!\1).)+)\1/u', join('|', array_map(fn($k)=> addcslashes($k, '!..~'), array_keys(Token::INLINE)))),
      'link' => '/(!?)\[([^\[\]]++|(?R))\]\((\S+?)\s*(?:\"(.*)\")?\)/u'
    ];
    
    $this->DOM  = $node->ownerDocument;
    $this->node = $node;
  } 
  
  public function parse(?DOMElement $node = null)
  {
    $node ??= $this->node;

    $text = $node->nodeValue;
    
    $matches = [
      ...$this->gather(self::$rgxp['link'], $text, [$this, 'link']),
      ...$this->gather(self::$rgxp['pair'], $text, [$this, 'basic']),
      ...$this->gather('/\{([a-z]+)\:(.+?)\}/u', $text, [$this, 'tag'])
    ];

    if ($node->nodeName == 'li')
      array_push($matches, ...$this->gather('/^\[([x\s])\](.*)$/u', $text, [$this, 'input']));
    
    usort($matches, fn($A, $B)=> $B[2] <=> $A[2]);

    foreach ($matches as $i => [$in, $out, $end, $elem]) {
      // skip nested.. parsed recursively
      if ($i > 0 && $in > $matches[$i-1][0]) {
        $matches[$i] = $matches[$i-1];
        continue;
      }

      $textnode = $node->firstChild;
      while ($textnode->nodeType !== XML_TEXT_NODE) $textnode = $textnode->nextSibling;
      $node->replaceChild($elem, $textnode->splitText($in)->splitText($out)->previousSibling);
      $this->parse($elem);
    }
    return $node;
  }
  
  static public function format($node) {
    return (new self($node))->parse();
  }
  
  public function gather($rgxp, $text, callable $callback)
  {
    preg_match_all($rgxp, $text, $matches, PREG_OFFSET_CAPTURE|PREG_SET_ORDER);
    return array_map(fn($match) => $callback($text, ...$match), $matches);
  }
  
  private function offsets($line, $match) {
    $in  = mb_strlen(substr($line, 0, $match[1]));
    $out = mb_strlen($match[0]);
    return [$in, $out, $in+$out];
  }
    
  private function basic($line, $match, $symbol, $text) {
    $node   = new DOMElement(Token::INLINE[$symbol[0]], htmlspecialchars(trim($text[0]), ENT_XHTML, 'UTF-8', false));
    return [...$this->offsets($line, $match), $node];
  }
  
  private function tag($line, $match, $tag, $text)
  {
    $text  = trim($text[0]);
    $clean = preg_replace('/(?:\s?\[([^\]]+)\]\s?)?/', '', $text); 
    $node = $this->DOM->createElement('span', htmlspecialchars($clean, ENT_XHTML, 'UTF-8', false));
    $title = str_replace(['[', ']'], '', $text);
    $node->setAttribute('title', str_replace(['‘', '’', '—'], ["'", "'", '--'], $title));
    $node->setAttribute('class', $tag[0]);
    return [...$this->offsets($line, $match), $node];
  }
  
  private function link($line, $match, $flag, $text, $url, $caption = null)
  {
    if ($flag[0]) {
      $node = $this->DOM->createElement('img');
      $node->setAttribute('src', $url[0]);
      $node->setAttribute('alt',  $text[0]);
    } else {
      $node = $this->DOM->createElement('a', htmlspecialchars(trim($text[0]), ENT_XHTML, 'UTF-8', false));
      $node->setAttribute('href', $url[0]);
    }
    
    if ($caption[0] ?? false) {
      $node->setAttribute('title', $caption[0]);
    }
    
    return [...$this->offsets($line, $match), $node];
  }

  private function input($line, $match, $checked, $text)
  {
    $node = $this->DOM->createElement('label', $text[0]);
    $input = $node->insertBefore($this->DOM->createElement('input'), $node->firstChild);
    $input->setAttribute('type', 'checkbox');
    if ($checked[0] != ' ') $input->setAttribute('checked', 'checked');
    $out = mb_strlen($match[0]);
    return [0, $out, $out, $node];
  }
}