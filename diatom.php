<?php libxml_use_internal_errors(true);

# Classes, in order of appearance:
# Template, Document (Text, Attr, Element), Data, Renderer, XMD (Inline), Model  
# Scroll to `try` block at the end for custom implementation/configuration

/****          ************************************************************************ TEMPLATE */

class Template {
  
  private $Src, $Doc, $slugs = [], $templates = [];
  

  // TODO make stricter what the constructor allows
  
  public function __construct($file, ?self $src = null) {
    if (! $file instanceof Document) {
      
      if ($file instanceof Element) {
        $this->Doc = new Document($file->export());
        $this->Doc->info = $file->ownerDocument->info; 
      }
      
      else $this->Doc = Document::open($file);

    }
    
    else $this->Doc = $file;
    
    $this->Src = $src;
    $this->Doc->documentElement->setAttribute('data-template', realpath('.').'/'. $this->Doc->info['src']);    
  }
  
  public function render($data = [], $parse = true): Document {
    
    foreach ($this->getTemplates('insert') as [$path, $ref]) {

      [$path, $xpath] = explode(':', $path.':');

      $doc = Document::open($path);
      
      if ($xpath && $doc->intact) {
        foreach ($doc->find($xpath) as $node) {  
          $this->import((new Self($node, $this))->render($data, false) , $ref, 'insertBefore');
        }
        $ref->parentNode->removeChild($ref);
      } else {
        $this->import((new Self($doc, $this))->render($data, false), $ref);
      }
      
    }
    
    foreach ($this->getTemplates('replace') as [$prop, $ref]) {
      if (isset($this->templates[$prop]) && $template = $this->templates[$prop]) {
        $this->import((new Self($this->templates[$prop], $this))->render($data, false), $ref->nextSibling);
        $ref->parentNode->removeChild($ref);
      }
    } 
    
    foreach ($this->getTemplates('iterate') as [$key, $ref]) {

      $view = new Self( $ref->parentNode->removeChild( $ref->nextSibling ), $this);

      foreach (Data::PAIR(explode(':', $key), $data) ?? [] as $datum) {
        $view->cleanup($this->import($view->render($datum), $ref, 'insertBefore'), true);
      }
      $ref->parentNode->removeChild($ref);
    }
    
    if ($parse) { try {
        foreach ($this->getSlugs() as [$node, $scope]) $node(Data::PAIR($scope, $data));
      } catch (UnexpectedValueException $e) {
        $this->cleanup($node($e->getMessage())->parentNode);
      }
      
      if (! $this->Src instanceof self) {
        $this->cleanup($this->Doc->documentElement, true);
      }
    }
    return $this->Doc;
  }
  
  public function set(string $key, $path = false): self {
    if ($path) $this->templates[$key] = $path;
    return $this;
  }
  
  private function cleanup(DOMNode $node, ?bool $delete = false): void {
    static $remove = [];
    if ($delete) {
      while ($path = array_pop($remove)) {
        $list = $node->ownerDocument->find($path, $node);
        $list[$list->length-1]->remove();
      }
    } else $remove[] = '..' . $node->getNodePath();
  }
  
  private function getTemplates($key): iterable {
    // consider a processing instruction
    // $xp  = '//processing-instruction($key)';
    // $xp .= ($key == 'iterate') ? " and not(./ancestor::*{$xp})" : '';
    // 7.4 Data::apply($this->Doc->find($xp), fn($stub) => [trim($stub->data), $stub]);
    $xp = "./descendant::comment()[ starts-with(normalize-space(.), '{$key}')"
           . (($key == 'iterate') ? ']' : 'and not(./ancestor::*/preceding-sibling::comment()[iterate])]');
     
    return Data::apply($this->Doc->find( $xp ), function ($stub) {
      return [preg_split('/\s+/', trim($stub->nodeValue))[1], $stub];
    });    
  }
  
  // Oy. Tokenizer, or perhaps entity search? note, if this is the one place that the __toString of Elements
  // is useful, consider writing it a different way.
  private function getSlugs(): iterable {
    return $this->slugs ?: ( function (&$out) {
      $xp = "substring(.,1,1)='[' and contains(.,'\$') and substring(.,string-length(.),1)=']' and not(*)";
      foreach ( $this->Doc->find("//*[{$xp}]|//*/@*[{$xp}]") as $var ) {
        preg_match_all('/\$+[\@a-z_:|0-9]+\b/i', $var( substr($var, 1,-1) ), $match, PREG_OFFSET_CAPTURE);
        foreach (array_reverse($match[0]) as [$k, $i]) {
          $N = $var->firstChild->splitText(mb_strlen(substr($var, 0, $i), 'UTF-8'))
                                ->splitText(strlen($k))->previousSibling;
          if (substr( $N( substr($N,1) ),0,1 ) != '$') $out[] = [$N, explode(':', str_replace('|', '/', $N))];
        }
      }
      return $out;
    })($this->slugs);
  }
    
  private function import(Document $import, DOMNode $ref, $swap = 'replaceChild'): DOMNode {
    return $ref->parentNode->{$swap}( $this->Doc->importNode($import->documentElement, true), $ref );
  }
}


/****          ************************************************************************ DOCUMENT */

class Document extends DOMDocument {
  const   XMLDEC   = LIBXML_COMPACT|LIBXML_NOBLANKS|LIBXML_NOENT|LIBXML_NOXMLDECL;
  public  $info  = [], $intact = true;
  private $xpath = null, $in = null,
          $props = [ 'preserveWhiteSpace' => false, 'formatOutput' => true ,
                     'resolveExternals'   => true , 'encoding'     => 'UTF-8' ];
  
  function __construct(string $xml, array $props = [], $editable = false) { parent::__construct('1.0', 'UTF-8');

    foreach (( $props + $this->props ) as $p => $value) $this->{$p} = $value;
    foreach (['Element','Text','Attr'] as       $c    ) $this->registerNodeClass("DOM$c", $c);

    // After setting properties and registering node classes, Load raw XML string
    if (! $this->loadXML($xml, self::XMLDEC)) throw new ParseError('DOM Parse Error');
    
    if ($editable) Renderer::editable($this);

  }

  public function save($path) {
    // consider how to implement $this->validate() based on how document was loaded.
    return  file_put_contents($path, $this->saveXML(), LOCK_EX);
  }
  
  public function find($exp, ?DOMNode $context = null) {
    
    if (! $this->xpath) $this->xpath = new DOMXpath($this);
    // 7.4 return ($this->xpath ??= new DOMXpath($this))->query($path, $context);
    if (! $result = $this->xpath->query($exp, $context))
      throw new Exception("Problem with xpath predicate: {$exp}");
    
    return $result;
  }
    
  public function select($exp, ?DOMNode $context = null)
  {
    return $this->find($exp, $context)[0] ?? null; 
  }
  
  public function claim(string $id): DOMElement {
    return $this->getElementById($id);
  }
  
  public function __toString() {
    return $this->saveXML($this->documentElement);
  }
  
  static public function open(string $path, $config = [], $editable = true) {
    $info = pathinfo($path);
    $data = file_get_contents($path);
    
    try {
      if ($info['extension'] == XMD::EXT) {
        $Doc = XMD::parse($data);
      } else {
        $Doc = new self($data, $config, $editable, $path);
      }
    } catch (ParseError $e) {
      $data = (array)libxml_get_errors()[0] + $info + ['path' => realpath($path)];
      $Doc  = (new Template(DOC['error']))->render($data);
      $Doc->intact = false;
    }
        
    foreach ($Doc->find("/processing-instruction()") as $pi)
      $Doc->info[$pi->target] = trim($pi->data);
    
    $Doc->info['src']  =  $path;
    $Doc->info['path'] = $info;
    $Doc->info['path']['title'] = $Doc->info['title'] ?? $Doc->info['path']['filename']; 
    return $Doc;
  }
    
  static public function errors(): array {
    return array_map(function ($e) use($path) { return (array)$e; }, libxml_get_errors());
  }
}


/****           ********************************************************************** INVOCABLE */

trait invocable {
  public function __invoke(?string $input): self {
    $this->nodeValue = htmlentities($input, ENT_COMPAT|ENT_XML1, 'UTF-8', false);
    return $this;
  }
  
  public function __toString(): string {
    return $this->nodeValue;
  }
}


/****      ******************************************************************************** TEXT */

class Text extends DOMText { use invocable; }


/****      ******************************************************************************** ATTR */

class Attr extends DOMAttr {
  use invocable;
  public function remove() {
    return ($elem = $this->ownerElement) ? $elem->removeAttribute($this->nodeName) : null;
  }
}


/****         ************************************************************************** ELEMENT */

class Element extends DOMElement implements ArrayAccess {
  use invocable;
  
  public function find(string $path) {
    return new Data($this->ownerDocument->find($path, $this));
  }
  
  public function export(): string {
    return $this->ownerDocument->saveXML($this);
  }

  public function offsetExists($key) {
    return $this->find($key)->count() > 0;
  }

  public function offsetGet($key, $create = false, $index = 0) {    

    if (($nodes = $this->find($key)) && ($nodes->count() > $index))
      return $nodes;
    else if ($create)
      return $this->appendChild(($key[0] == '@') ? new Attr(substr($key, 1)) : new Element($key));
    else 
      throw new UnexpectedValueException($key);
  }

  public function offsetSet($key, $value) {
    return $this->offsetGet($key, true)($value);
  }

  public function offsetUnset($key) {
    return $this[$key]->remove();
  }
  
  public function remove() {
    return ($parent = $this->parentNode) ? $parent->removeChild($this) : null;
  }
  
  public function __call($key, $args): DOMNode {
    return $this->offsetSet($key, ...$args);
  }
}


/****      ******************************************************************************** DATA */

// TODO make this a singleton.
class Data extends ArrayIterator {
  
  static public $store = [];
  static private $XML  = [];
         private $maps = [];
  
  
  static public function PAIR(array $namespace, $data) {
    while ($key = array_shift($namespace)) {
      if (! isset($data[$key])) throw new UnexpectedValueException($key);
      $data  = is_callable($data[$key]) ? $data[$key]($key) : $data[$key];
    }
    return $data;
  }
  
  static public function model($name) {
    if (! isset(self::$XML[$name])) throw new InvalidArgumentException('model does not exist');
    if (! self::$XML[$name] instanceof Document) {
      self::$XML[$name] = Document::open(self::$XML[$name], ['validateOnParse' => true]);
      foreach (self::$XML[$name]->find("/processing-instruction('php')") as $php) eval($php->data);
    }
    return new $name(self::$XML[$name]->documentElement);
  }
  
  static public function use(string $name, string $src): void {
    self::$XML[$name] = $src;
    self::$store[$name] = function($name) { return self::model($name); }; 
  }
  
  static public function apply(iterable $data, callable $callback): self {
    return (new self($data))->map($callback);
  }
  
  public function __construct(iterable $data) {
    parent::__construct(! is_array($data) ? iterator_to_array($data) : $data);
  }

  public function current() {
    $current = parent::current();
    foreach ($this->maps as $callback) $current = $callback($current);
    return $current;
  }
  
  public function map(callable $callback) {
    $this->maps[] = $callback;
    return $this;
  }
  
  public function sort(callable $callback) {
    if (!empty($this->maps)) return (new Data ($this))->sort($callback);

    $this->uasort($callback);
    return $this;
  }
  
  public function filter(callable $callback) {
    return new CallbackFilterIterator($this, $callback);
  }

  public function limit($start, $length) {
    return new LimitIterator($this, $start, $length);
  }
  
  public function __toString() {
    return (string) $this->current();
  }
}


/****          ************************************************************************ RENDERER */

/*
  Brief: This class should be where processing of HTML occurs. That means, render as a term should only imply
  the involvement of a markup language (ie, no plaintext or 'markdown-ish' stuff which can be 'scanned/evaluated/parsed)
  
  1. constructs generators that returns new nodes, ie; foreach(renderer->processSections() as $section)...
  2. find content such as comment nodes for insertion
  3. move content, such as meta/link elements, or things from item 2
  4. do arbitrary proccessing related to 1, such as creating footnotes, etc, figure->figcaption, rendering tables, etc

*/

class Renderer {
  
  static public function editable(Document $document) {
    // look for all nearly terminal nodes; ie., contain text, but whose parents do not contain text
    // This is a 'preflight' renderer—it would become less-useful on a already-revised documents
    foreach ($document->find('//*/text()[not(../../text()[normalize-space(.)])]/..') as $node) {
      $node->setAttribute('data-xpath', $node->getNodePath());
      $node->setAttribute('data-line', $node->getLineNo());
    }

    return $document;
  }
  
  static public function sectionize(Document $document) {
    foreach ($document->find('//article/h2[not(ancestor::section)]') as $mark) {
      $section = $document->createElement('section');
      $mark = $mark->parentNode->replaceChild($section, $mark);
      $section->appendChild($mark);
      $sibling = $section->nextSibling;
      while($sibling && $sibling->nodeName != 'h2') {
        $next = $sibling->nextSibling;
        $section->appendChild($sibling);
        $sibling = $next;
      }
    }
    
    // find all sections without id
    foreach($document->find('//section[not(@id)]/h2') as $idx => $h2)
      $h2->parentNode->setAttribute('id', strtoupper(preg_replace('/[^a-z0-9]/i','', $h2)));
    
    // yield $element;
    return $document;
  }
  
  static public function organize(Document $doc) {
    if (! $head = $doc->select('/html/head')) return $doc;

    foreach ($doc->find('//style') as $node) {
      $node->firstChild->data = preg_replace_callback('(\{[^{]+\})', function($matches) {
        return implode('', array_map(function($line) { return trim($line); }, explode("\n", $matches[0])));
      }, preg_replace('/\n\s*\n/', "\n", $node->nodeValue));
      $head->appendChild($node);
    }
    return $doc;
  }
}



/****     ********************************************************************************** XMD */

/*
  TODO
  [ ] TYPOGRAPHY: replace ' with actual apostrophe, -- with n-dash --- with mdash
  [ ] deal with & breaking everything
  [ ] think of syntax to post-render certain lists into definition lists
  [ ] consider |mark| into post-render <strong><strong>mark</strong></strong> (****mark****) thing
  [ ] deal with abstract HTML class (by removing it and placing code elsewhere)
*/

class XMD {
  const EXT   = 'xmd';
  public $doc = null;
  
  public function __construct($text, $root = 'article') {
    $this->doc = new Document("<{$root}/>");
    $prior = $this->doc->documentElement;
    foreach ($this->split($text) as $block) {
      $prior = $block->render($prior);
    }
  }
  
  static public function parse($text) {
    $instance = new self($text);
    return $instance->doc;
  }
  
  private function split(string $text) {
    $filtered = preg_replace(['/¶|\r\n|\r/u', '/\t/'], ["\n",'  '], $text); 
    return array_map( function($text) {
      //7.4  implode('|', array_map(fn($re):string => "({$re})", BLOCK);
      $exp = implode('|', array_map(function($regex) { 
        return "({$regex})";
      }, BLOCK::tags));
      
      $key   = preg_match("/\s*{$exp}/Ai", $text, $list, PREG_OFFSET_CAPTURE) ? count($list) - 2 : 0;
      $match = array_pop($list) ?? [null, 0];
      return new Block($this->doc, array_keys(BLOCK::tags)[$key], $text, ...$match);
      
    }, array_filter(explode("\n", $filtered), 'XMD::notEmpty'));
  }
  
  public function notEmpty($string) {
    return ! empty(trim($string));
  }
    
  public function __toString() {
    return $this->doc->saveXML();
  }
}

/****       ****************************************************************************** LEXER */

class Block {
  const tags = [
    'li'         => '[0-9]+\.|- ',
    'h'          => '#{1,6}',
    'pre'        => '\s{4}',
    'blockquote' => '>',
    'hr'         => '-{3,}',
    'pi'         => '\?[a-z]+ ',
    'p'          => '\S',
  ];
  public $doc, $name, $text, $depth, $symbol, $value, $reset = false, $context = null;
  
  public function __construct(DOMDocument $document, string $name, string $text, string $symbol, int $indent) {
    $offset = strlen($symbol);
    $this->doc = $document;
    $this->name   = $name != 'h' ?  $name :  "h" . $offset;
    $this->text   = trim(substr($text, $indent + (($name == 'p') ? 0 : $offset)));
    $this->depth  = floor($indent/2);
    $this->symbol = trim($symbol);
    $this->value  = new Inline($this->text, $this->doc);
  }
  
  public function render($previous) {
    if ($this->name == 'li')
      return $this->renderLI($previous);
    else if ($previous instanceof DOMElement) 
      $this->context =  $previous;
    else if ($this->name != $previous->name && $previous->reset)
      $this->context = $previous->reset;
    else 
      $this->context = $previous->context;

    if ($this->name == 'pi') {
      $this->doc->insertBefore(new DOMProcessingInstruction(substr($this->symbol, 1), $this->text), $this->doc->firstChild);
    } else 
      $this->value->inject($this->context->appendChild(new \DOMElement($this->name)));
    
    return $this;
  }
  
  public function getType() {
    return $this->symbol == '-' ? 'ul' :'ol';
  }
  
  public function makeParent(\DOMElement $context, int $depth = 0): \DOMElement {
    $parent = $context->appendChild(new \DOMElement($this->getType()));
    if ($depth > 0) $context->setAttribute('class', 'nested');
    return $parent;
  }
  
  public function renderLI($previous) {
    $this->reset = $previous->reset;
    $depth       = $this->depth;
    if ($previous->name != 'li') {
      $this->context = $this->makeParent($previous->context);
      $this->reset = $previous->context;    
    } else if ($previous->depth < $depth) { 
      $this->context = $this->makeParent($previous->context->appendChild(new \DOMElement('li')) ,$depth);
    } elseif ($previous->depth > $depth) {
      $this->context = $previous->context;
      while($depth++ < $previous->depth) {
        $this->context = $this->context->parentNode->parentNode;
      }
      if ($this->getType() != $this->context->nodeName) {
        // will break if indentation is sloppy
        $this->context = $this->makeParent($this->context->parentNode);  
      }
    } elseif ($this->getType() != $previous->getType()) {
      $this->context = $this->makeParent($previous->context->parentNode);
    } else {
      $this->context = $previous->context;
    }
    
    $this->value->inject($this->context->appendChild(new \DOMElement('li')));
    return $this;
  }
}


/****        **************************************************************************** INLINE */
class Inline {
  const tags = [
    'q'      => '"([^"]+)"',
    'a'      => '(!?)\[([^\)^\[]+)\]\(([^\)]+)(?:\"([^"]+)\")?\)',
    'input'  => '^\[([x\s])\](.*)$',
    // TODO, right now this does img too.. would rather something <whatever.jpg> do the trick, as a general embedder
    'strong' => '\*\*([^*]+)\*\*',
    'em'     => '\*([^\*]+)\*',
    'mark'   => '\|([^|]+)\|',
    'time'   => '``([^``]+)``',
    'code'   => '`([^`]+)`',
    's'      => '~~([^~~]+)~~',
    'abbr'   => '\^\^([^\^]+)\^\^',
    'sup'    => '\+\+([^\+]+)\+\+',
  ];
    
  private $text, $node;
  
  public function __construct($text, DOMDocument $doc) {
    $this->node = $doc->createDocumentFragment();
    $this->frag = $doc->createDocumentFragment();
    $this->frag->textContent = $text;
    $this->doc  = $doc;
    $this->text = $text;
  }
  
  public function getFlags() {
    count_chars(implode('', INLINE::tags), 3);
  }
    
  public function inject($elem) {
    if (empty($this->text)) return;
    $this->node->appendXML($this->parse($this->text));
    return $elem->appendChild($this->node);
  }
    
  public function parse($text) {
    foreach (INLINE::tags as $name => $re) {
      if (preg_match_all("/{$re}/u", $text, $hits, PREG_SET_ORDER) > 0)
        foreach ($hits as $hit) $text = str_replace($hit[0], self::{$name}($this->doc, ...$hit), $text);
    }
    return $text;
  }
  
  static public function a($doc, $line, $flag, $value, $url, $title = '') {
    [$name, $attr] = $flag ? ['img', 'src'] : ['a', 'href'];
    $elem = $doc->createElement($name, $value);
    $elem->setAttribute($attr, $url);
    if ($title) $elem->setAttribute('title', $title);
    return $elem->export();
  }
  
  static public function time($doc, $line, $value) {
    $time = strtotime($value);
    $elem = $doc->createElement('time', date('D M j++S++', $time));
    $elem->setAttribute('datetime', date(DATE_W3C, $time));
    return $elem->export();
  }
  
  static public function code($doc, $line, $value) {
    $time = strtotime($value);
    $elem = $doc->createElement('code');
    $elem->appendChild($doc->createCDATAsection($value));
    return $elem->export();
  }
  
  static public function input($doc, $line, $value, $label) {
    $elem = $doc->createElement('label', ' ');
    $elem->appendChild(new Element('span'))->appendChild(self::makeNode($label, $doc));
    $input = $elem->insertBefore($doc->createElement('input'), $elem->firstChild);
    $input->setAttribute('type', 'checkbox');
    if ($value != ' ') {
      $input->setAttribute('checked', 'checked');
    }
    return $elem->export();
  }
  
  static private function makeNode($text, $doc) {   
    if (strpbrk($text, '<>')) {
      $data = $doc->createDocumentFragment();
      $data->appendXML($text);
    } else 
      $data = new Text(trim($text));

    return $data;
  }
  
  static public function __callStatic($name, $args) {
    [$doc, $line, $value] = $args;
    $elem = $doc->createElement($name);
    $elem->appendChild(self::makeNode($value, $doc));
    return $elem->export();
  }
}


/*************       ********************************************************************* MODEL */

abstract class Model implements ArrayAccess {
  static public $src;
  protected $context;

  public function __construct($input) { 
    $this->context = ($input instanceof Element) ? $input : self::$src->claim($input);
  }
  
  protected function collect(string $expression) {
    // 7.4 return Data::apply($this->context->find($expression), fn($item) => new self($item));
    return Data::apply($this->context->find($expression), function($item) {
      return new static($item);
    });
  }
      
  public function offsetExists($key) {
    return isset($this->context[$key]) || method_exists($this, "get{$key}");
  }

  public function offsetGet($key) {
    $method  = "get{$key}";
    return method_exists($this, $method) ? $this->{$method}($this->context) : $this->context[$key];
  }

  public function offSetSet($key, $value) {
    return $this->context[$key] = $value;
  }

  public function offsetUnset($key) {
    unset($this->context[$key]);
    return true;
  }
  
  final public function __toString() {
    return $this->context['@id'];
  }
}
/****       **************************************************************************************/

// TODO this should be instantiable, and perhaps what is happening in the Data::apply(glob...
//      should be handled by this class
class Route {
  static public  $key;
  static private $routes    = [];
  static private $callbacks = [];
  
  private function __construct() { }
  
  
  static public function gather($exp, $priority_key) {
    $queue = new SplPriorityQueue;
    $docs  = Data::apply(glob($exp), 'Document::open');
    foreach($docs as $doc) {
      $info = $doc->info;
      self::$routes[$info['path']['title']] = $doc;
      
      if (isset($info['publish']) && $info['publish'] == 'true') {
        $queue->insert($info, -$info[$priority_key]);
      }
    }
    return $queue;
  }
  
  static public function delegate($yield, $layout) {
    // TODO null should give index. mismatch should give 404
    $key = key($_GET);
    return $layout->set($yield, self::configure($key, $_GET[$key] ?? null));
  }
  
  static private function configure($key, $params = null) {
    $doc = self::$routes[$key] ?? null;
    return array_key_exists($key, self::$callbacks) ? self::$callbacks[$key]($doc, $params) : $doc;
  }
  
  static public function process($key, callable $callback) {
    self::$callbacks[$key] = $callback;
  }
}
