<?php

class Parser {
  const ROOT = 'article';
  
  public $document, $context;
      
  public function __construct(string $input, ?Element $context = null) {
    $this->document = $context ? $context->ownerDocument : new Document;
    $this->context  = $context ?? $this->document;

    $path  = substr($input, -3) == '.md' && strpos($input, "\n") === false;
    $input = $path ? new SplFileObject($input) : array_map(fn($line) => $line . "\n", preg_split('/\R/u', $input));
    
    foreach ($this->scan($input) as $block) {
      $block->process($this);
    }
  }
  
  static public function load(File $file) {
    $ext = ['css' => '<style/>', 'js' => '<script/>', 'txt' => '<pre/>'];
    
    if ($file->type == 'md')
      return (new self($file->uri))->document;
    
    elseif ($tag = $ext[$file->type] ?? false) {
      $DOM = new Document($tag);
      $DOM->documentElement->appendChild(new Text($file->body));
      return $DOM;
    }
    
    throw new Error("{$file->type} Not Supported", 500);
  }
  
  
  static public function check(Document $output, string $type)
  {
    if ($type == 'json')
      $output = json_encode(simplexml_import_dom($output));
    
    else if ($type == 'md')
      $output = Plain::convert($output);
    
    return $output;
  }
  
  static public function markdown($node)
  {
    return (new Inline($node))->parse();
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
    return $this->document->saveXML();
  }
  
}

class Token {
  
  const BLOCK = [
    'name' => [  'p'  ,  'ol'   ,   'ul'  ,  'h%d' ,'CDATA', 'dl' , 'BLOCK' ,  'hr'  ,'comment', 'pi' ,     'table'      , 'p' ],
    'rgxp' => ['[A-Z]', '\d+\. ', '[-*] ' ,'#{1,6}','`{3}' , ':'  ,  '[>+] ', '-{3,}',  '\/\/' , '\?' , '\|(?=.+\|.+\|$)', '\S'],
    'trap' => [ false ,  true   ,  true   ,  false , true  , true ,   true  ,  false , false   , false,     true         ,false],
  ];
  
  const INLINE = [
    '~~' => 's',
    '~'  => 'dfn',
    '**' => 'strong',
    '*'  => 'em',
    '__' => 'b',
    '_'  => 'cite',
    '``' => 'kbd',
    '`'  => 'code',
    '^^' => 'small',
    '^'  => 'sup',
    '||' => 'u',
    '|'  => 'mark',
    '""' => 'q',
  ];
  
  const TRANSLATE = [
    '%' => 'abbr',
    ':' => 'span',
    '=' => 'data',
    '@' => 'time',
    '#' => 'aria',
  ];
  
  public $flag, $trim, $depth, $text, $name, $value, $context = false, $element = null;
  
  function __construct($data) {

    foreach ($data as $prop => $value) $this->{$prop} = $value;
    $this->value =  trim(substr($this->text, $this->name == 'p' ? 0 : $this->trim));
    
    if ($this->context) {
      if ($this->name == 'CDATA') {
        $name = trim(substr($this->text, 3));
        $this->element = in_array($name, ['style', 'script']) ? $name : 'pre';
      } elseif ($this->name == 'table') {
        $this->element = 'tr';
        $this->value = trim($this->value, '|');
      } else if ($this->name == 'BLOCK') {
        $this->name    = ['+' => 'details', '>' => 'blockquote'][trim($this->flag)];
        $this->element = ['details' => 'summary', 'blockquote' => 'p'][$this->name];
      }  else {
        $this->element = $this->flag == ':' ? 'dt' : 'li';
      }
    }
  }
}


class Block {
  const INDENT = 4;
  private $token = [], $trap, $precursor = null;
  private static $rgxp;

  public function __construct(?Token $token = null, $trap = null)
  {
    self::$rgxp ??= sprintf("/^\s*(?:%s)/i", implode('|', array_map(fn($x) => "($x)", Token::BLOCK['rgxp'])));
    $this->trap = $trap;
    if ($token) $this->push($token);
  }
  
  public function parse($text)
  {
    if (preg_match(self::$rgxp, $text, $list, PREG_OFFSET_CAPTURE) < 1) return false;

    [$symbol, $offset] = array_pop($list); // last match contains match & offset: [string $symbol, int offset]
    $idx  = count($list) - 1;
    $trim = strlen($symbol);
    return new Token ([
      'name'    => sprintf(Token::BLOCK['name'][$idx], $trim),
      'context' => Token::BLOCK['trap'][$idx],
      'flag'    => $symbol,
      'trim'    => $offset + $trim,
      'depth'   => ceil($offset / self::INDENT) + 1,
      'text'    => $text,
    ]);
  }
  
  public function push(Token $token): Block
  {
    $this->token[] = $this->precursor = $token;
    return $this;
  }
  
  public function capture(string $line)
  {
    if (! $token = $this->parse($line)) {
      if ($this->trap && $this->precursor->name === 'CDATA') $this->push(new Token(['text' => $line]));
      return $this;
    }
    
    if ($token->context || $this->trap)
      return $this->evaluate($token);

    return empty($this->token) ? $this->push($token) : new self($token);
  }
  
  public function evaluate(Token $token)
  {
    if ($this->trap || $token->name === 'CDATA') {

      if ($token->name === 'CDATA') {
        
        if ($this->trap === null && $this->trap = $token->flag) {
          $token->text = "\n";
          return $this->push($token);
        } elseif ($this->trap == trim($token->text))
          return new self;
        
        $token->name = 'CDATA';
        
      } elseif (substr($this->trap, 0, 5) === 'block') {
        

        if (! $token->context && $token->depth > $this->precursor->depth && substr($this->trap, 5) === 'dl') {
          $token->name    = 'dl';
          $token->element = 'dd';
          $token->depth   = $this->precursor->depth;
        } else if ($token->name != $this->precursor->name && ($token->depth == 1 || $token->depth < $this->precursor->depth)) {
          return new self($token, $token->context ? substr($this->trap, 0, 5) . $token->name : null);
        }
      }

      return $this->push($token);
    }
      
    
    if ($token->name == 'blockquote' || $token->name == 'details' || $token->name == 'dl') {
      
      $this->trap = 'block' . $token->name;
      
      if ($this->precursor) {
        return new self($token, $this->trap); 
      }
      
    } elseif ($this->precursor && $token->name != $this->precursor->name && $token->depth == $this->precursor->depth) {
      return new self($token);
    }
      

    return $this->push($token);
  }
   
  public function process(Parser $parser): void
  {
    $this->parser = $parser;
    $context = $this->parser->context;
    
    foreach($this->token as $idx => $token) {
      if ($context instanceof DOMText) {
        $context->appendData($token->text);
        continue;
      }
      
      $delta   = ($this->token[$idx-1] ?? (object)['depth' => 1])->depth - $token->depth;
      $context = $this->append($context, $token, $delta);
    }
  }
  
  private function append(DOMNode $context, Token $token, int $delta)
  {
    if ($token->name == 'pi') {
      $context->appendChild(new DOMProcessingInstruction(...explode(' ', $token->value, 2)));
      return $context;
    }
    
    if ($context instanceof Document) {
      $name =  $context->evaluate("/processing-instruction('root')") ?: Parser::ROOT;
      $context = $this->parser->context = $context->appendChild(new Element($name));
    }
    
    if ($token->name == 'comment') {
      $context->appendChild(new DOMComment($token->value));
      return $context;
    }
    
    if ($token->element && $token->name != 'CDATA' && ($context->nodeName != $token->name || $delta != 0) && $context->nodeName != 'tbody') {
      if ($delta > 0) {
        $context = $context->select(join('/', array_fill(0, $delta, '../..')));
      } else {
        $name = $context->lastChild->nodeName;
        if ($delta < 0 && ($name == $token->element || ($token->name == 'dl' && $name == 'dd'))) {
          $context = $context->lastChild;
          $context->appendChild($context->ownerDocument->createElement('span')->adopt($context));
        }
        $context = $context->appendChild(new Element($token->name));
      }
    }

    if ($context->nodeName == 'tbody' && $token->element != 'tr') {
      $context = $context->select('../..');
    }
    
    $element = $context->appendChild(new Element($token->element ?? $token->name));
    
    if ($token->name === 'hr')
      return $context;
    
    if ($token->name === 'CDATA')
      return $element->appendChild(new DOMText($token->text));
    
    if ($token->element == 'tr') {
      if (preg_match('/\-{3,}/', $token->value) && $swap = $element->previousSibling) {
        $head = new Element('thead');
        $body = new Element('tbody');
        $row = $head->appendChild($context->replaceChild($head, $swap));
        $context->replaceChild($body, $element);
        $context = $body;
      } else
        foreach (explode('|', $token->value) as $cell)
          $element->appendChild(new Element($context->nodeName == 'tbody' ? 'td' :'th', trim($cell)));

    } else {
      $text = preg_replace(['/\\\([~*_`^|])/', '/(?<=\s)\'/u', '/(?<=\S)\'/u'], ['$1', '‘', '’'], $token->value);
      $element($text);
    
      if (preg_match('/^[^!~*\[_`^|{<"\\\=]*+(.)/', $text, $offset, PREG_OFFSET_CAPTURE))
        (new Inline($element))->parse(null, $offset[1][1]);
    }
    return $context;
  }
}


class Inline {
  const  RGXP = [
    'link'     => '/(!?)\[([^\[\]]++|(?R))\]\((\S+?)\s*(?:\"(.*)\")?\)/u',
    'basic'    => '/(?<!\\\)([~*_`^|]+|"")((?:(?!\1).)+)\1/u',
    'clarify'  => '/\{([-\w, .]+) ?([:%@#=]) ?([^{}]++|(?R)*)\}/u',
    'autolink' => '/<((?>https?:\/)?\/([^<>]+))>/',
    'breaks'   => '/ +(\\\) /',
  ];
  
  private $DOM, $node;
  
  public function __construct(DOMElement $node)
  {
    $this->DOM  = $node->ownerDocument;
    $this->node = $node;
  } 
  
  public function parse(?DOMElement $node = null, int $offset = 0)
  {
    $node ??= $this->node;
    $text = $node->nodeValue;
    $mark = [];

    foreach (self::RGXP as $key => $exp)
      array_push($mark, ...$this->gather($exp, $text, [$this, $key], $offset));

    if ($node->nodeName == 'li')
      array_unshift($mark, ...$this->gather('/^\[([x\s])\](.*)$/u', $text, [$this, 'input'], $offset));
    
    usort($mark, fn($A, $B)=> $B[2] <=> $A[2]);

    foreach ($mark as $i => [$in, $out, $end, $elem]) {
      // skip nested.. parsed recursively
      if ($i > 0 && $end > $mark[$i-1][0]) {
        $mark[$i] = $mark[$i-1];
        continue;
      }

      $textnode = $node->firstChild;
      while ($textnode->nodeType !== XML_TEXT_NODE) $textnode = $textnode->nextSibling;

      if ($split = $textnode->splitText($in)->splitText($out))
        $node->replaceChild($elem, $split->previousSibling);
      
      if ($elem->nodeName != 'code' && $elem->nodeValue)
        $this->parse($elem);
      
    }
    return $node;
  }
  
  static public function format($node) {
    return (new self($node))->parse();
  }
  
  public function gather($rgxp, $text, callable $callback, int $offset)
  {
    preg_match_all($rgxp, $text, $matches, PREG_OFFSET_CAPTURE|PREG_SET_ORDER);
    return array_map(fn($match) => $callback($text, ...$match), $matches);
  }
  
  private function offsets($line, $match, $node) {
    $in  = mb_strlen(substr($line, 0, $match[1]));
    $out = mb_strlen($match[0]);
    return [$in, $out, $in+$out, $node];
  }
  
  private function makeNode($name, $value, array $attrs = [])
  {
    $node = $this->DOM->createElement($name, $value);
    if ($value) $node->nodeValue = htmlspecialchars(trim($value), ENT_XHTML, 'UTF-8', false);
    foreach ($attrs as $attr => $value) $node->setAttribute($attr, $value);
    return $node;
  }
  
    
  private function basic($line, $match, $symbol, $text)
  {
    $mark = substr($symbol[0], 0, 2 - strlen($symbol[0]) % 2);
    return $this->offsets($line, $match, $this->makeNode(Token::INLINE[$mark], $text[0]));
  }
  
  private function breaks($line, $match, $text) {
    return $this->offsets($line, $match, new Element('br'));
  }
  
  public function autolink($line, $match, $pathordomain, $url) {
    return $this->link($line, $match, [false], $url, $pathordomain);
  }
  
  private function link($line, $match, $flag, $text, $url, $caption = null)
  {
    $args = $flag[0] ? ['img', null,     ['src'  => $url[0], 'alt' => $text[0]]]
                     : ['a'  , $text[0], ['href' => $url[0]                   ]];

    if ($caption[0] ?? false) $args[2]['title'] = $caption[0];
    
    return $this->offsets($line, $match, $this->makeNode(...$args));
  }


  private function clarify($line, $match, $tag, $symbol, $text)
  {
    $node = call_user_func([$this, Token::TRANSLATE[$symbol[0]]], trim($tag[0]), $text[0]);
    return $this->offsets($line, $match, $node);
  }

  private function time($format, $timestring)
  {
    $date = new DateTime($timestring);
    return $this->makeNode('time', $date->format($format), [
      'datetime' => $date->format(DATE_ATOM),
      'title' => $timestring,
    ]);
  }
  
  private function abbr($title, $text) {
    return $this->makeNode('abbr', $text, ['title' => $title]);
  }
  
  public function span($name, $text) {
    return $this->makeNode('span', $text, ['class' => $name]);
  }
  
  public function data($value, $text) {
    return $this->makeNode('data', $text, ['value' => $value]);
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

class Plain {
  
  const METHODS = ['paragraphs', 'rules', 'list', 'headings', 'table', 'CDATA', 'blocks'];
  
  static public function convert(DOMNode $node) {
    
    [$DOM, $context] = $node instanceof Document
                     ? [$node, $node]
                     : [$node->ownerDocument, $node];
    
    $instance = new self($DOM);
    
    foreach (self::METHODS as $method)
      call_user_func([$instance, $method], $context);
    
    return (string) $instance;
  }
  
  private $document;
  
  private function __construct(Document $DOM) {
    $this->document = $DOM;
    $this->basic = array_flip(Token::INLINE);
    $this->query = join('|', array_keys($this->basic)) . '|span';
  }
  
  public function __toString() {
    if ($head = $this->document->select('head')) $head->remove();
    return html_entity_decode(str_replace(['‘', '’', '—'], ["'", "'", '--'], trim(strip_tags($this->document))));
  }
  
  public function prefix($context, array $ancestors = [], $offset = 0) {
    $exp = join('|', array_map(fn($tag) => 'ancestor::'.$tag, $ancestors));
    $num = $this->document->evaluate("count({$exp})", $context) - $offset;
    return "\n" . str_repeat(' ', max(0, $num * Block::INDENT - 2));
  }
  
  public function paragraphs(DOMNode $context) {
    foreach ($context->find('.//p|.//figure') as $node) {
      $indent = $this->prefix($node, ['blockquote', 'details']);
      $node->parentNode->replaceChild(new Text($indent.$this->inline($node) . "\n"), $node);
    }
  }
  
  public function blocks(DOMNode $context)
  {
    $key = ['blockquote' => '>', 'details' => '+'];
    foreach ($context->find('.//blockquote|.//details') as $node) {
      $node->parentNode->replaceChild(new Text("\n\n{$key[$node->nodeName]} " . trim($node->nodeValue) . "\n"), $node);
    }
  }
  
  public function list(DOMNode $context)
  {
    // move nest ol/ul's out of li's for proper parsing
    foreach ($context->find('.//li/ul|.//li/ol|.//dd/dl') as $node)
      $node->parentNode->parentNode->insertBefore($node, $node->parentNode->nextSibling);
    
    
    foreach ($context->find(".//li") as $node) {
      $indent = $this->prefix($node, ['ul', 'ol', 'blockquote', 'details'], 1);
      
      $prefix = ($node->parentNode->nodeName == 'ol')
        ? preg_replace('/.*li(\[(\d+)\])(?1)?$/', '\2', $node->getNodePath().'[1]') . '.'
        : '-';
      $node("{$indent}{$prefix} " . $this->inline($node));
    }
    
    
    $type = ['dt' => ':', 'dd' => '::'];
    foreach ($context->find('.//dt|.//dd') as $node) {
      $indent = $this->prefix($node, ['blockquote', 'details', 'dl'], 1);
      $key = $type[$node->nodeName];
      $node("{$indent}{$key} " . $this->inline($node));
    }
    
    foreach($context->find('.//ul|.//ol|.//dl') as $node)
      $node->parentNode->replaceChild(new Text($node->nodeValue. "\n"), $node);
    
  }
  
  public function table(DOMNode $context)
  {
    foreach ($context->find('.//table') as $node) {
      $col = array_fill(0, $node->find('.//th')->length, 0);
      
      $indent = $this->prefix($node, ['details']);
      
      for ($i=1; $i <= count($col); $i++)
        $col[$i-1] = max(array_map(fn($n) => strlen($n($this->inline($n))), iterator_to_array($node->find(".//tr/*[$i]"))));

      foreach ($node->find('.//tr') as $row) {
        foreach ($row->childNodes as $i => $cell) {
          $cell(' ' . str_pad($cell, $col[$i],' ', STR_PAD_LEFT). ' |');
        }
        
        $row->parentNode->replaceChild(new Text("{$indent}|" .$row->nodeValue ), $row);
      }
      
      $head = $node->select('thead');
      $divider = substr_replace(preg_replace('/[^|\n]/', '-', $head), $indent, 0, strlen($indent));
      $node->replaceChild(new Text("\n" . $head . $divider), $head);
      
      $node->appendChild(new Text("\n"));
    }
  }
  
  public function headings(DOMNode $context)
  {
    foreach ($context->find(".//*[substring-after(name(), 'h') > 0]") as $node)
      $node("\n".str_repeat('#', substr($node->nodeName, 1)) . ' ' . $this->inline($node) . "\n");
  }
  
  public function rules($context) {
    foreach ($context->find('.//hr|.//br') as $node)
      $node($node->nodeName == 'hr' ? "\n\n----\n\n" : ' \ ');
  }
  
  
  public function references($context, $name, $attr, $flag):void
  {
    foreach ($context->find($name) as $node) {
      $text  = $name == 'a' ? $this->inline($node) : $node->getAttribute('alt');
      $title = $node->hasAttribute('title') ? ' "'.$node->getAttribute('title').'"' : '';
      $context->replaceChild(new Text('%s[%s](%s%s)', $flag, $text, $node->getAttribute($attr), $title), $node);
    }
  }
  
  public function input($context):void
  {
    foreach($context->find('label/input[@type="checkbox"]') as $node) {
      $flag = $node->hasAttribute('checked') ? 'x' : ' ';
      $context->replaceChild(new Text('[%s] %s', $flag, $this->inline($node->parentNode)), $node->parentNode);
    }
  }
  
  public function basic($context):void {
    foreach ($context->find($this->query) as $node) {
      $flag = trim($this->basic[$node->nodeName] ?? '');
      $context->replaceChild(new Text('%s%s%1$s', $flag, $this->inline($node)), $node);
    }
  }
  
  public function tags($context):void {
    foreach ($context->find('data[@value]') as $node)
      $context->replaceChild(new Text('{%s: %s}', $node->getAttribute('value'), $node->getAttribute('title') ?: $this->inline($node)), $node);
  }
  
  public function inline(Element $node):string
  {
    $this->basic($node);
    $this->references($node, 'a', 'href', '');
    $this->references($node, 'img', 'src', '!');
    $this->tags($node);
    $this->input($node);
    return trim($node->nodeValue, '\ ');
  }
  
  public function CDATA($context)
  {
    foreach ($context->find('.//pre|.//style|.//script') as $node) {
      $flag = $node->nodeName == 'pre' ? '' : $node->nodeName;
      $node("\n```{$flag}$node->nodeValue```\n");
    }
    
    foreach ($context->find('/processing-instruction()|.//comment()') as $node) {
      if ($node->nodeName == '#comment')
        $node->parentNode->replaceChild(new Text("\n// {$node->data}\n"), $node);
      else
        $this->document->documentElement->insertBefore(new Text("?{$node->target} {$node->data}\n"), $this->document->documentElement->firstChild);
    }
  }
}
