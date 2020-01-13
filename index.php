<?php namespace Diatom;

$_CONFIG = [
  'author'  => 'Diatom Development``',
  'time' => new \DateTime,
];

// Routing
$_ROUTES = [
  'index' => function ($template) {
    // do things for template
  },
];

// Helpers
$_HELP = [
  'time' => function (...$arguments) {
  },
];


/****          ************************************************************************ TEMPLATE */
class Template {
  
  private $parent, $document, $slugs = [], $templates = [];
  
  static public function __callStatic(string $dir, $path): self {
    return new Self(sprintf('%s/%s', $dir, implode('/', $path)));
  }

  public function __construct($input, ?self $parent = null) {
    $this->document = new Document($input);
    $this->parent   = $parent;
  }
  
  public function render($data = [], $parse = true): Document {
    foreach ($this->getTemplates('insert') as [$path, $ref]) {
      $this->import((new Self($path, $this))->render($data, false), $ref);
    }
    
    foreach ($this->getTemplates('replace') as [$prop, $ref]) {
      if (isset($this->templates[$prop]) && $template = $this->templates[$prop]) {
        if (! $template instanceof Document) {
          $template = (new Self($this->templates[$prop], $this))->render($data, false);
        }
        $this->import($template, $ref->nextSibling);
        $ref->parentNode->removeChild($ref);
      }
    } 
    
    foreach ($this->getTemplates('iterate') as [$key, $ref]) {
      $view = new Self( $ref -> parentNode -> removeChild( $ref -> nextSibling ), $this);
      foreach ($data[$key] ?? [] as $idx => $datum) {
        $view->cleanup($this->import($view->render($datum), $ref, 'insertBefore'), $idx+1);
      }
      $ref->parentNode->removeChild($ref);
    }
    
    if ($parse) {
      foreach ($this->getSlugs() as [$node, $scope]) { try {
        $node(Data::PAIR($scope, $data));
      } catch (\UnexpectedValueException $e) {
        $list = $this->cleanup($node->parentNode);
      }}
      
      if (! $this->parent instanceof self) {
        $this->cleanup($this->document->documentElement, 1);
      }
    }
    return $this->document;
  }
  
  public function set(string $key, $path): self {
    $this->templates[$key] = $path;
    return $this;
  }
  
  private function cleanup(\DOMNode $node, ?int $idx = null): void {
    static $remove = [];
    if ($idx) {
      while ($path = array_pop($remove)) {
        $list = $node->ownerDocument->query($path, $node);
        if ($list->length == $idx) $list[$idx-1]->remove();
      }
    } else $remove[] = '..' . $node->getNodePath();
  }
  
  private function getTemplates($key): iterable {
    $query = "./descendant::comment()[ starts-with(normalize-space(.), '{$key}')"
           . (($key == 'iterate') ? ']' : 'and not(./ancestor::*/preceding-sibling::comment()[iterate])]');

    return (new Data($this->document->query( $query )))->map( function ($stub) {
      return [preg_split('/\s+/', trim($stub->nodeValue))[1], $stub];
    });    
  }
  
  private function getSlugs(): iterable {
    return $this->slugs ?: ( function (&$out) {
      $query = "substring(.,1,1)='[' and contains(.,'\$') and substring(.,string-length(.),1)=']' and not(*)";
      foreach ( $this->document->query("//*[{$query}]|//*/@*[{$query}]") as $slug ) {        
        preg_match_all('/\$+[\@a-z_:|0-9]+\b/i', $slug( substr($slug, 1,-1) ), $match, PREG_OFFSET_CAPTURE);
      
        foreach (array_reverse($match[0]) as [$k, $i]) {
          $N = $slug->firstChild->splitText(mb_strlen(substr($slug, 0, $i), 'UTF-8'))->splitText(strlen($k))->previousSibling;
          if (substr( $N( substr($N,1) ),0,1 ) != '$') $out[] = [$N, explode(':', str_replace('|', '/', $N))];
        }
      }
      return $out;

    })($this->slugs);
  }
    
  private function import(Document $import, \DOMNode $ref, $swap = 'replaceChild'): \DOMNode {
    return $ref->parentNode->{$swap}( $this->document->importNode($import->documentElement, true), $ref );    
  }
}

libxml_use_internal_errors(true);

/****          ************************************************************************ DOCUMENT */
class Document extends \DOMDocument {
  const   XMLDEC   = LIBXML_COMPACT|LIBXML_NOBLANKS|LIBXML_NOENT|LIBXML_NOXMLDECL;
  private $xpath   = null, $input = null,
          $options = [ 'preserveWhiteSpace' => false, 'formatOutput' => true ,
                       'resolveExternals'   => true , 'encoding'     => 'UTF-8',
                     ];
  
  function __construct($input, $opts = [], $method = 'load') { parent::__construct('1.0', 'UTF-8');
    
    $this->input = $input;
    
    foreach (array_replace($this->options, $opts) as $property => $value)
      $this->{$property} = $value;
    
    foreach (['Element','Text','Attr'] as $classname)
      $this->registerNodeClass("\\DOM{$classname}", "\\Diatom\\{$classname}");
    
    if ($input instanceof Element) {
      $this->input = $input->ownerDocument->saveXML($input);
      $method = 'loadXML';
    } else if (! file_exists($input)) throw new \InvalidArgumentException("Cannot load $input}");

    if (! $this->{$method}($this->input, self::XMLDEC)) {
      $view = Template::pages('error.html')->render(['errors' => $this->errors()]);
      $this->appendChild($this->importNode($view->documentElement, true));
    }
  }

  public function save($path = null) {
    return $this->validate() && file_put_contents($path ?: $this->input, $this->saveXML(), LOCK_EX);
  }

  public function query(string $path, \DOMElement $context = null): \DOMNodeList {
    return ($this->xpath ?: ($this->xpath = new \DOMXpath($this)))->query($path, $context);
  }
  
  public function claim(string $id): \DOMElement {
    return $this->getElementById($id);
  }

  public function errors(): Data {
    return (new Data(libxml_get_errors()))->map(function ($error) { return (array) $error; });
  }
  
  public function __toString() {
    return $this->saveXML();
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
class Text extends \DOMText {
  use invocable;
}

/****      ******************************************************************************** ATTR */
class Attr extends \DOMAttr {
  use invocable;
  public function remove() {
    return ($elem = $this->ownerElement) ? $elem->removeAttribute($this->nodeName) : null;
  }
}

/****         *************************************************************************** ELEMENT */
class Element extends \DOMElement implements \ArrayAccess {
  use invocable;
  
  public function selectAll(string $path) {
    return new Data($this->ownerDocument->query($path, $this));
  }
  
  public function merge(array $data) {
    foreach ($data as $key => $value) {
      if (is_array($value)) {
        // this is a live document, so each find would result in the index being correct after the append
        $nodes = $this->offsetGet($key, true, $idx);
        foreach ($value as $idx => $v) {
          
          if ($nodes->count() > $idx) {
            // figure out a way to remove extra nodes - ie., deleting content
          }
          $nodes[$idx] = $v;
        }
      } else {
        $this[$key] = $value;
      }
    }
  }

  public function offsetExists($key) {
    return $this->selectAll($key)->count() > 0;
  }

  public function offsetGet($key, $create = false, $index = 0) {    
    if (($nodes = $this->selectAll($key)) && ($nodes->count() > $index))
      return $nodes;
    else if ($create)
      return $this->appendChild(($key[0] == '@') ? new Attr(substr($key, 1)) : new Element($key));
    else 
      throw new \UnexpectedValueException($key);
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
  
  public function __call($key, $args): \DOMNode {
    return $this->offsetSet($key, ...$args);
  }
}

/****      ******************************************************************************** DATA */
class Data extends \ArrayIterator {
  
  static private $store = [];
  
  static public function PAIR(array $namespace, $data) {
    while ($key = array_shift($namespace)) {
      if (! isset($data[$key]) && ! array_key_exists($key, $data)) {
        throw new \UnexpectedValueException($key);
      }
      $data = $data[$key];      
    }
    return $data;
  }
  
  static public function Use(string $src, ?string $path = null) {
    $document = self::$store[$src] ?? self::$store[$src] = new Document($src, ['validateOnParse' => true]);
    return $path ? new self($document->query($path)) : $document;
  }
  
  private $maps = [];
  
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
    $this->uasort($callback);
    return $this;
  }
  
  public function filter(callable $callback) {
    return new \CallbackFilterIterator($this, $callback);
  }

  public function limit($start, $length) {
    return new \LimitIterator($this, $start, $length);
  }
  
  public function merge(array $data) {
    // this will be called when element is being merged against a list of data. 
  }
  
  public function __invoke($param) {
    return $this->current()($param);
  }
  
  public function __toString() {
    return (string) $this->current();
  }
}

/****          ************************************************************************* REGISTRY */
trait Registry {
  public $data = [];
  public function __get($key) {
    return $this->data[$key] ?? null;
  }
  
  public function __set($key, $value) {
    $this->data[$key] = $value;
  }
  
  public function merge(array $data) {
    return array_merge($this->data, $data);
  }
}

class Help implements \ArrayAccess {
  public function offsetExists ($key) {}
  public function offsetSet ($key, $value) {}
  public function offsetUnset ($key) {}
  public function offsetGet ($method) {
    if (! is_callable($method)) throw new \Exception("{$method} is not callable");
    return function (array $value) {
      $method(...$value);
    };
  }
    
}


/*

helper methods in template? Say I wanted to the first character of a word—to accomplish, I've 
always just generated a method in each individual model that might do a one off. (this can mildly bloat models, though this is a really really small problem)

<p>[$item:firstletteroftitle]</p>

this requires adding a method to every model whenever that feature is needed. something like

<p>[$help\substr\item:title|0|1]</p>

would attempt to do the function call automatically through composition of functions.


the pair method would have to do something like:
if(is_callable($out)) {
  $out = $out(self::pair([$namespace, $data), ...explode('|', $namespace))]);
}

Thoughts: the template syntax, while intriguing, is looking a bit messy. I like the recursion of the
PAIR method, and think that might work out elegantly. Continuing to ponder...
*/





echo Template::pages(($_GET['route'] ?: 'index') . '.' . ($_GET['ext'] ?: 'html'))->render($_CONFIG);

