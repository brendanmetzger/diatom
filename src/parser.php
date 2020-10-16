<?php

class Parser {

  public $DOM, $context = null;
      
  public function __construct(string $input, $context = '<article/>', string $xpath = '/*') {
    if ($context instanceof Element) {
      $this->DOM     = $context->ownerDocument;
      $this->context = $context;
      
    } else {
      $this->DOM     = new Document($context);
      $this->context = $this->DOM->select($xpath) ?? $this->DOM->documentElement;
    }
    
    $path  = substr($input, -3) == '.md' && strpos($input, "\n") === false;
    $input = $path ? new SplFileObject($input) : array_map(fn($line) => $line . "\n", preg_split('/\R/u', $input));
    foreach ($this->scan($input) as $block) $block->process($this->context);
  }
  
  static public function load(File $file) {
    $ext = ['css' => '<style/>', 'js' => '<script/>', 'txt' => '<pre/>'];
    
    if ($file->type == 'md')
      return (new self($file->uri))->DOM;
    
    else if (isset($ext[$file->type])) {
      $DOM = new Document($ext[$file->type]);
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
      $output = (string) (new Plain($output))->blocks()->rules()->list()->headings()->table()->CDATA();
    
    
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
    return $this->DOM->saveXML();
  }
  
}

class Token {
  
  const BLOCK = [
    'name' => [  'p'  ,   'dl'   ,  'ol'   ,   'ul'  ,  'h%d' ,'CDATA', 'BLOCK' ,  'hr'  ,'comment', 'pi' ,     'table'       , 'p' ],
    'rgxp' => ['[A-Z]','[: ]{2}', '\d+\. ', '[-*] ' ,'#{1,6}','`{3}' ,  '[>+] ', '-{3,}',  '\/\/' , '\?' , '^\|(?=.+\|.+\|$)', '\S'],
    'trap' => [ false ,   true   ,  true   ,  true   ,  false , true  ,   true  ,  false , false   , false,     true          ,false],
  ];
  
  const INLINE = [
    '~~' => 's',
    '~'  => 'dfn',
    '**' => 'strong',
    '*'  => 'em',
    '_'  => 'cite',
    '__' => 'u',
    '``' => 'time',
    '`'  => 'code',
    '^^' => 'abbr',
    '^'  => 'small',
    '|'  => 'mark',
    '||' => 'b',
    '""' => 'q',
  ];
  
  public $flag, $trim, $depth, $text, $name, $rgxp, $value, $context = false, $element = null;
  
  function __construct($data) {
    foreach ($data as $prop => $value) $this->{$prop} = $value;
    $this->value =  trim(substr($this->text, $this->name == 'p' ? 0 : $this->trim));
    if ($this->context) {
      if ($this->name == 'CDATA') {
        $name = trim(substr($this->text, 3));
        $this->element = in_array($name, ['style', 'script']) ? $name : 'pre';
      } else if ($this->name == 'table') {
        $this->element = 'tr';
        $this->value = trim($this->value, '|');
      } else if ($this->name == 'BLOCK') {
        // TODO, this is going to be a more complicated capture based on indentation
        $this->name = ['+' => 'details', '>' => 'blockquote'][trim($this->flag)];
        $this->element = ['details' => 'summary', 'blockquote' => 'p'][$this->name];
      }  else {
        print_r($this->flag);
        $this->element = [': '=>'dt', '::'=>'dd'][$this->flag] ?? 'li';
      }
        
    }
  }
}


class Block {
  const INDENT = 4;
  private $token = [], $trap = false, $cursor = null;
  private static $rgxp;

  public function __construct(?Token $token = null) {
    self::$rgxp ??= sprintf("/^\s*(?:%s)/i", implode('|', array_map(fn($x) => "($x)", Token::BLOCK['rgxp'])));

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
      'depth'   => floor($offset / self::INDENT) + 1,
      'text'    => $text,
    ]);
  }
  
  public function push(Token $token): Block {
    $this->token[] = $this->cursor = $token;
    return $this;
  }
  
  public function capture(string $line)
  {
    $line = str_replace(['&nbsp;', '&mdash;', '&ndash;', '”', '“'], [' ', '—', '–', '"', '"'], $line);
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
    foreach($this->token as $idx => $token) {
      if ($context instanceof DOMText) {
        $context->appendData($token->text);
        continue;
      }
      
      $delta   =  ($this->token[$idx-1] ?? (object)['depth' => 1])->depth - $token->depth;
      $context = $this->append($context, $token, $delta);
    }
  }
  
  private function append(DOMNode $context, Token $token, int $delta)
  {
    if ($token->name == 'comment') {
      $context->appendChild(new DOMComment($token->value));
      return $context;
    }

    if ($token->name == 'pi' && $doc = $context->ownerDocument) {
      $doc->insertBefore(new DOMProcessingInstruction(...explode(' ', $token->value, 2)), $doc->firstChild);
      return $context;
    }
    
    if ($token->element && $token->name != 'CDATA' && ($context->nodeName != $token->name || $delta != 0) && $context->nodeName != 'tbody') {
      if ($delta > 0)
        $context = $context->select(join('/', array_fill(0, $delta, '../..')));
      else {
        if ($delta < 0) {
         $context = $context->lastChild;
         $context->appendChild($context->ownerDocument->createElement('span')->adopt($context));
        }
        $context = $context->appendChild(new Element($token->name));
      }
    }
    
    $element = $context->appendChild(new Element($token->element ?? $token->name));
    
    if ($token->name === 'hr')
      return $context;
    
    if ($token->name === 'CDATA')
      return $element->appendChild(new DOMText($token->text));
    
    
    
    if ($token->element == 'tr') {
      if (preg_match('/\-{3,}/', $token->value)) {
        $head = new Element('thead');
        $body = new Element('tbody');
        $row = $head->appendChild($context->replaceChild($head, $element->previousSibling));
        $context->replaceChild($body, $element);
        $context = $body;
      } else
        foreach (explode('|', $token->value) as $cell)
          $element->appendChild(new Element($context->nodeName == 'tbody' ? 'td' :'th', trim($cell)));

    } else {
      $text = preg_replace(['/\\\([~*_`^|])/', '/(?<=\s)\'/u', '/(?<=\S)\'/u', '/\s?--\s?/u'], ['$1', '‘', '’', '—'], $token->value);
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
    'tag'      => '/\{([-\w]+)\: ?([^{}]++|(?R)*)\}/u',
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
    
  private function basic($line, $match, $symbol, $text) {
    $mark = substr($symbol[0], 0, 2 - strlen($symbol[0]) % 2);
    $node = new Element(Token::INLINE[$mark], htmlspecialchars(trim($text[0]), ENT_XHTML, 'UTF-8', false));
    return $this->offsets($line, $match, $node);
  }
  
  private function breaks($line, $match, $text)
  {
    return $this->offsets($line, $match, new Element('br'));
  }
  
  private function tag($line, $match, $tag, $text)
  {
    $text  = trim($text[0]);
    $clean = preg_replace('/(?:\s?\[([^\]]+)\]\s?)?/', '', $text); 
    $node = $this->DOM->createElement('data', htmlspecialchars($clean, ENT_XHTML, 'UTF-8', false));
    $title = str_replace(['[', ']'], '', $text);
    $node->setAttribute('title', str_replace(['‘', '’', '—'], ["'", "'", '--'], $title));
    $node->setAttribute('value', $tag[0]);
    return $this->offsets($line, $match, $node);
  }
  
  public function autolink($line, $match, $pathordomain, $url)
  {
    return $this->link($line, $match, [false], $url, $pathordomain);
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
    
    return $this->offsets($line, $match, $node);
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
  
  private $document;
  
  public function __construct(Document $DOM) {
    $this->document = $DOM;
    $this->basic = array_flip(Token::INLINE);
    $this->query = join('|', array_keys($this->basic));
  }
  
  public function __toString() {
    if ($head = $this->document->select('head')) $head->remove();
    return str_replace(['‘', '’', '—'], ["'", "'", '--'], trim(html_entity_decode(strip_tags($this->document))));
  }
  
  public function blocks():self {
    foreach ($this->document->find('//p|//figure') as $node)
      $node->parentNode->replaceChild(new Text("\n".$this->inline($node)."\n"), $node);
    return $this;
  }
  
  public function list():self
  {
    // move nest ol/ul's out of li's for proper parsing
    foreach ($this->document->find('//li/ul|//li/ol') as $node)
      $node->parentNode->parentNode->insertBefore($node, $node->parentNode->nextSibling);
    
    foreach ($this->document->find("//li") as $node) {
      $indent = str_repeat(' ', ($node->find('ancestor::ul|ancestor::ol')->length - 1) * Block::INDENT);
      $prefix = ($node->parentNode->nodeName == 'ol')
        ? preg_replace('/.*li(\[(\d+)\])(?1)?$/', '\2', $node->getNodePath().'[1]') . '.'
        : '-';
      $node("\n{$indent}{$prefix} " . $this->inline($node));
    }
    
    foreach($this->document->find('//ul|//ol') as $node)
      $node->parentNode->replaceChild(new Text($node->nodeValue. "\n"), $node);
    
    return $this;
  }
  
  public function table():self
  {
    foreach ($this->document->find('//table') as $node) {
      $col = array_fill(0, $node->find('//th')->length, 0);
      

      for ($i=1; $i <= count($col); $i++)
        $col[$i-1] = max(array_map(fn($n) => strlen($n($this->inline($n))), iterator_to_array($node->find(".//tr/*[$i]"))));

      foreach ($node->find('.//tr') as $row) {
        foreach ($row->childNodes as $i => $cell) {
          $cell('| ' . str_pad($cell, $col[$i],' ', STR_PAD_LEFT). ' ');
        }
        $row->parentNode->replaceChild(new Text($row->nodeValue . "|\n"), $row);
      }
      
      $head = $node->select('thead');
      $node->replaceChild(new Text("\n" . $head . preg_replace('/[^|\n]/', '-', $head)), $head);

    }
    return $this;
  }
  
  public function headings():self
  {
    foreach ($this->document->find("//*[substring-after(name(), 'h') > 0]") as $node)
      $node("\n".str_repeat('#', substr($node->nodeName, 1)) . ' ' . $this->inline($node) . "\n");
    return $this;
  }
  
  public function rules():self {
    foreach ($this->document->find('//hr|//br') as $node)
      $node($node->nodeName == 'hr' ? "\n\n----\n\n" : ' \ ');
    return $this;
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
      $flag = $this->basic[$node->nodeName];
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
  
  public function CDATA():self
  {
    foreach ($this->document->find('//pre|//style|//script') as $node) {
      $flag = $node->nodeName == 'pre' ? '' : $node->nodeName;
      $node("\n```{$flag}$node->nodeValue```\n");
    }
    
    foreach ($this->document->find('/processing-instruction()|//comment()') as $node) {
      if ($node->nodeName == '#comment')
        $node->parentNode->replaceChild(new Text("\n// {$node->data}\n"), $node);
      else
        $this->document->documentElement->insertBefore(new Text("?{$node->target} {$node->data}\n"), $this->document->documentElement->firstChild);
    }
    return $this;
  }
}
