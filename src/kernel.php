<?php


set_include_path(dirname(__FILE__));
libxml_use_internal_errors(true);
spl_autoload_register();




interface routable
{
  public function __invoke($template);
  public function error($info):Exception;
  public function compose($payload, bool $default);
}

/**
 * Route | Provides to enable default and callback routing
 *
**/

class Route {
  
  const INDEX = 'index';
  static private $paths = [];
  
  static public function delegate(routable $response)
  {
    if (! $route = self::$paths[$response->action] ?? false)
      throw $response->error(self::$paths);
    
    return $response->compose($route->fulfill($response), $route->path == Route::INDEX);
  }
  
  static public function set($path, ?callable $callback = null, array $config = []) {
    $instance = self::$paths[$path] ??= new self($path, $config['publish'] ?? 0);
    $instance->info +=  $config;

    if ($callback === null) {
      $instance->template = $config['src'];
    } else {
      $instance->info['route']   = $instance->path;
      $instance->info['title'] ??= $instance->path;
      
      $instance->then($callback);
    }

    return $instance;
  }

  // Note: this is really an alias for the above, but I like for writing quick bin/task stuff.
  // Warning: __callStatic to set route can potentially collide with an existing Route method
  static public function __callStatic($action, $arguments) {
    return self::set($action, ...$arguments);
  }
  
  static public function gather(array $files)
  {
    // this could be tallied on `set` so that it doesn't have to be mapped
    $dynamic = array_map(fn($obj) => $obj->publish, self::$paths);
    $static  = array_map('filemtime', $files);
    $stash   = sys_get_temp_dir() . '/' . md5(join($dynamic+$static));
    
    if (file_exists($stash)) {
      $routes = json_decode(file_get_contents($stash), true);
      foreach ($routes as $path => $info)
        if (isset($info['path']))
          self::set($path, null, $info);

    } else {
      
      foreach(Data::apply($files, 'Document::open') as $DOM) {
        $path = $DOM->info['route'] ??= $DOM->info['path']['filename'];
        self::set($path, null, $DOM->info);
      }
      
      uasort(self::$paths, fn($A, $B) => ($A->publish) <=> ($B->publish));

      $routes = array_map(fn($R) => $R->info, self::$paths);
      file_put_contents($stash, json_encode($routes));
    }
    
    return array_filter($routes, fn($route) => $route['publish'] ?? false);
  }
  
  
  /**** Instance Properties and Methods **************************************/
  
  private $handle = null, $template = null;

  public $path, $publish, $info = [];

  private function __construct(string $path, int $publish = 0) {
    $this->path    = $path;
    $this->publish = $publish;
  }
  
  public function then(callable $handle) {
    $this->handle[] = $handle;
  }
  
  public function fulfill(routable $response, $out = null, int $i = 0) {
        
    if ($this->handle !== null) {
      $out = $response->params;
      do
       $out = $this->handle[$i++]->call($response, ...(is_array($out) ? $out : [$out]));
      while (isset($this->handle[$i]) && ! $response->fulfilled);
    }
    
    $response->fulfilled = true;
    $response->layout  ??= $this->info['layout'] ?? self::$paths[self::INDEX]->template;
    $response->render  ??= $this->info['render'] ?? null;
      
    return $out ?? $response($this->template);
  }
  
}


/**
 * File | Construct an object that represents something that can be openclose
 * shows up alot i other classes ie., funcion someMethod(File $arg) {...}
 *
**/

class File
{
  public const MIME = [
    'png'  => 'image/png',
    'jpeg' => 'image/jpeg',
    'jpg'  => 'image/jpeg',
    'webp' => 'image/webp',
    'ico'  => 'image/vnd',
    'woff2'=> 'font/woff2',
    'pdf'  => 'application/pdf',
    'mp4'  => 'video/mp4',
    'mov'  => 'video/quicktime',
    'm4a'  => 'audio/m4a',
    'mp3'  => 'audio/mp3',
    'xml'  => 'application/xml',
    'html' => 'text/html',
    'js'   => 'application/javascript',
    'json' => 'application/json',
    'css'  => 'text/css',
    'svg'  => 'image/svg+xml',
    'zip'  => 'application/zip',
    'gz'   => 'application/x-gzip',
    'md'   => 'text/plain',
    'txt'  => 'text/plain',
  ];
    
  public $id, $uri, $url, $type, $info, $mime, $local = true, $size = 0, $body = '';

  public function __construct(string $identifier, ?string $content = null)
  {
    $this->uri   = $identifier;
    $identifier  = parse_url($identifier);
    $this->info  = $identifier + pathinfo($identifier['path'] ?? '/');
    $this->type  = strtolower($this->info['extension'] ?? 'html');
    $this->info['scheme'] ??= ($this->type == 'gz' ? 'compress.zlib' : 'file');
    $this->local = substr($this->info['scheme'], 0, 4) != 'http';
    $this->url = $this->info['url'] = $this->local ? $this->info['scheme'].'://' . realpath($this->uri) : $this->uri;
    $this->mime  = self::MIME[$this->type];
    $this->id    = md5($this->url);
    if ($content) $this->setBody($content);
  }
  
  static public function load($path)
  {
    $instance = new static($path);
    
    if (! $content = file_get_contents($instance->url))
      throw new Error('Bad path: ' . $path);

    return $instance->setBody($content);
  }
  
  public function setBody(string $content): File {
    $this->body = $content;
    $this->size = strlen($content);
    return $this;
  }
  
  public function save(bool $overwrite = false) {
    if (! $overwrite && is_file($this->uri))
      throw new Error("File cannot be written", 1);
    return file_put_contents($this->uri, $this->body, LOCK_EX);
  }
  
  public function verify(string $hash, string $algo = 'md5') {
    return hash($algo, $this->body) === trim($hash, '"');
  }
  
  public function __toString() {
    return $this->body;
  }
}


/**
 * Request | This is used to configure an variable request (like a web server) via the constructor
 *
**/ 

class Request
{
  const REGEX = '/^([\w\/-]*+)(?:\.(\w{1,4}))?$/i';
  const TYPE  = 'html';
  
  public $uri, $method, $data = '', $basic = false, $headers = [];
  
  public function __construct(array $headers)
  {
    $uri = parse_url(urldecode($headers['REQUEST_URI']));

    $this->method  = $headers['REQUEST_METHOD'] ?? 'GET';
    $this->headers = $headers;

    preg_match(self::REGEX, trim($uri['path'], '/ .'), $match, PREG_UNMATCHED_AS_NULL);
    
    $this->uri   = $match[0] ?: '/';
    $this->route = $match[1] ?: Route::INDEX; 
    $this->type  = $match[2] ?: self::TYPE;
    $this->mime  = $headers['CONTENT_TYPE'] ?? File::MIME[$this->type] ?? File::MIME[self::TYPE];
    $this->basic = $headers['HTTP_YIELD']   ?? ($this->mime != 'text/html');
    
    // can be POST, PUT or PATCH, which all contain a body
    if ($this->method[0] === 'P' && ($headers['CONTENT_LENGTH'] ?? 0) > 0)
      $this->data = file_get_contents('php://input');
  }
    
  static public function GET($uri, array $data = [], array $headers = []): Document
  {
    $response = new Response(new Request(array_merge($headers,[
      'REQUEST_URI'    => $uri,
      'REQUEST_METHOD' => 'GET',
      'CONTENT_TYPE'   => $headers['content-type'] ?? null,
      'HTTP_YIELD'     => true,
    ])), $data);

    return Route::delegate($response);
  }
  
  public function __toString() {
    return $this->uri;
  }
}




/**
 * Response | finds correct template object, renders it, tracks headers...
**/

class Response extends File implements routable
{
  use Registry;
  
  public $action, $params, $request, $headers = [], $fulfilled = false, $layout = null, $render = null;
  
  private $templates = [];
  
  public function __construct(Request $request, array $data = [])
  {
    parent::__construct($request->uri);
    $this->merge($data);
    $this->request = $request;
    $this->params  = explode('/', $request->route);
    $this->action  = strtolower(array_shift($this->params));
    $this->id      = md5(join($request->headers));
  }
  
  public function yield($key, $template) {
    $this->templates[$key] = $template;
  }
  
  public function __invoke($path) {
    // if we are here, there is no callback specified OR the callback did not return a value
    return $path ? Document::open($path) : new Document("<p>\u{26A0} /{$this->action}</p>");
  }
  
  public function error($info): Exception {
    return new Exception("'{$this->action}' was not found\n", 404);
  }
  
  public function header($resource, $header)
  {
    if (! empty(trim($header))) {
      $split = strpos($header, ':') ?: 0;
      $key   = $split ? strtolower(substr($header, 0, $split)) : 'status'; 
      $this->headers[$key] = trim($split ? substr($header, $split+1) : $header);
    }
    // this is required by the cURL callback (see @HTTP class and cURL documentation)
    return strlen($header);
  }
  
  public function compose($payload, bool $default)
  {
    // if payload is not a DOM component, no processing to do
    if (! $payload instanceof DOMNode) return $payload;
    
    
    if ($this->request->basic) {
       //no layout needed, just use payload document
      $layout = new Template($payload, $this->templates);
    } else {
      // find main document to use as layout
      $layout = new Template(Document::open($this->layout));

      // make sure we aren't putting the layout into itself
      if (! $default)
        $layout->set(Template::YIELD, $payload); 
    }
    
    // add in additional templates specified with yield method
    foreach ($this->templates as $key => $template)
      $layout->set($key, $template);
    
    // render and transform the document layout
    $output = $layout->render($this->data + $payload->info);
    $output = Render::transform($output, $this->render);
    
    // convert if request type is not markup
    return Parser::check($output, $this->request->type);
  }
}


/**
 * Controller | Usually extended, but can be instantiatied @see Route class.
 * 
**/

abstract class Controller
{
  protected $response;

  public function __get($key) {
    return $this->response->{$key};
  }

  public function yield($key, $value) {
    return $this->response->yield($key, $value);
  }
  
  public function index() {
    throw new Exception('Not Implemented', 501);
  }
  
  public function call(routable $response, $action = 'index', ...$params)
  {
    $this->response = $response;
    $this($action, $params);
  }
  
  public function __invoke($action, $params)
  {
    $this->action = strtolower($action);
    if (! is_callable([$this, $this->action])) throw new Exception("'{$action}' not found", 404);
    return  $this->{$this->action}(...$params);
  }
}

/**
 * Redirect | use as a controller, or throw anywhere to get a redirect going
 *
**/

class Redirect extends Exception {
  const STATUS    = ['permanent' => 301, 'temporary' => 302, 'other' => 303];
  public $headers = [
    ['Cache-Control: no-store, no-cache, must-revalidate, max-age=0'],
    ['Cache-Control: post-check=0, pre-check=0', false],
    ['Pragma: no-cache'],
  ];
  
  public function __construct(string $location, $code = 'temporary') {
    $this->headers[] = ["Location: {$location}", false, self::STATUS[$code]];
  }
  
  public function call(routable $response) {
    throw $this;
  }
  
  public function __invoke() {
    foreach ($this->headers as $header) header(...$header);
  }
}



/**
 * Template | rarely need to interact with this directly, it is responsible for plopping
 *          | data variables into the appropriate Document objects
 *
**/

class Template
{
  public const YIELD = '-default-view-object-';
  
  private $DOM, $templates = [], $slugs = [], $cache = null;
  
  public function __construct(DOMnode $input, ?array $templates = [])
  {
    if ($input instanceof Element) {
      $this->DOM = new Document($input->export());
      $this->DOM->info = $input->ownerDocument->info ?? null;
    } else {
      $this->DOM = $input;
    }
    
    $this->templates += $templates;
  }
  
  
  public function reset() {
    $this->DOM->replaceChild($this->cache->cloneNode(true), $this->DOM->firstChild);
  }

  
  public function render($data = [], $parse = true): Document
  {
    foreach ($this->getStubs('insert') as [$cmd, $path, $xpath, $context]) {
      
      $DOM = is_file($path) ? Document::open($path) : Request::GET($path, $data);
      $ref = $context->parentNode;
      foreach ($DOM->find($xpath) as $node) {
        if (!$node instanceof Text) $node = (new self($node))->render($data, false)->documentElement;
        $ref->insertBefore($this->DOM->importNode($node, true), $context);
      }
      $ref->removeChild($context);
    }
    
    foreach ($this->getStubs('yield') as [$cmd, $prop, $exp, $ref]) {
      if ($DOM = $this->templates[$prop ?? Template::YIELD] ?? null) {
        $context = ($exp !== '/') ? $ref : $ref->nextSibling;
        $node    = (new self($DOM, $this->templates))->render($data, false)->documentElement;
        $ref->parentNode->replaceChild($this->DOM->importNode($node, true), $context);

        if ($ref !== $context) $ref->parentNode->removeChild($ref);
      }
    }

    foreach ($this->getStubs('iterate') as [$cmd, $key, $exp, $ref]) {
      $context  = $slug = $ref->nextSibling;
      
      $template = new self($context);
      $template->cache = $template->DOM->documentElement->cloneNode(true);
      $invert   = $exp !== '/';
      
      foreach (Data::fetch($key, $data) ?? [] as $datum) {
        if ($import = $template->render($datum, true)->documentElement) {
          $node = $this->DOM->importNode($import, true);
          $slug = $context->parentNode->insertBefore($node, $invert ? $slug : $context);
          $template->reset();
        }
      }
      
      $context->remove();
      $ref->parentNode->removeChild($ref);
    }
    
    if ($parse) $this->parse($data);
    
    return $this->DOM;
  }
  
  # Note, this has a DOMNode as typecase, but should be Document|Element in 8.0
  public function set(string $key, DOMNode $stub = null) {
    $this->templates[$key] = $stub;
  }
  
  private function parse($data)
  {
    foreach ($this->getSlugs() as $path => $slugs) {
      if ($node = $this->DOM->select($path)) {
        $text = $node->nodeValue;
        foreach ($slugs as [$key, $offset]) {
          $replacement = Data::fetch($key, $data);
          if ($replacement === null && $node->remove()) continue 2;
          $text = substr_replace($text, $replacement, ...$offset);
        }
        $node($text);
        // Parser::markdown($node($text)));
      }
    }
  }
  
  private function getStubs($key): iterable
  {
    $exp = "comment()[starts-with(normalize-space(.), '{$key}')";
    if ($key == 'iterate')
      $exp .= " and not(ancestor::*/preceding-sibling::{$exp}])";
      
    return Data::apply($this->DOM->find("//{$exp}]"), function($node) {
      $data = preg_split('/\s+/', trim($node->data), 3);
      return $data + [1 => null, 2 => '/', 3 => $node];
    });
  }

  protected function getSlugs(): iterable
  {
    /*
      TODO mark nodes containing replacements as uneditable (remove @data-path attr prolly)
    */
    if (empty($this->slugs)) {
      $xp = "contains(.,'\${') and not(*)";
      foreach ( $this->DOM->find("//*[{$xp} and not(self::script or self::code)]|//*/@*[{$xp}]") as $var ) {
        $path  = $var->getNodePath();
        $this->slugs[$path] ??= [];
        
        preg_match_all('/\$\{[^}]+\}+/i', $var, $match, PREG_OFFSET_CAPTURE);
        
        foreach (array_reverse($match[0]) as [$k, $i]) {

          $split = [mb_strlen(substr($var, 0, $i)), mb_strlen($k)];
          $node  = $var->firstChild->splitText($split[0])->splitText($split[1])->previousSibling;
          $key   = substr($node->nodeValue, 2, -1);
          
          if ($key[0] == '$') {
            foreach($this->slugs[$path] as &$item) $item[1][0] -= 3;
            if ($this->cache instanceof Element)
              $this->cache->nodeValue = str_replace($key, substr($key, 2, -1), $this->cache);
            
            $node($key);
            continue;
          }
          
          $this->slugs[$path][] = [$key, $split];
        }
        
      }
    }
    return $this->slugs;
  }
}


/**
 * Document | Loads and creates documents that can be searched and modified.
 *          | Extends the language-supplied DOMDocument and several subclasses
 *
**/

class Document extends DOMDocument
{
  static private $cache = [];
  // accepts string|File
  static public function open($path, $opt = [])
  {
    $key = is_string($path) ? $path : $path->id;
    
    if (self::$cache[$key] ?? false) return self::$cache[$key];
    
    $file = $path instanceof File ? $path : File::load($path);

    try {
      if (! $file->local)
        $file->body = preg_replace('/\sxmlns=(\"|\').*?(\1)/', '', $file->body);

      $DOM = (substr($file->mime, -2) == 'ml') ? new self($file->body, $opt) : Parser::load($file);
    } catch (ParseError $e) {
      $err = (object)libxml_get_errors()[0];
      $hint = substr(file($path)[$err->line-1], max($err->column - 10, 0), 20);
      throw new ErrorException($err->message . " in {$path}, around: '{$hint}'", 500, E_ERROR, realpath($path), $err->line, $e);
    }

    foreach ($DOM->find("/processing-instruction()") as $pi)
      $DOM->info[$pi->target] = trim($pi->data);
    
    $DOM->info['src']     = $path;
    $DOM->info['path']    = $file->info;
    $DOM->info['title'] ??= $DOM->info['path']['filename'];
    
    Render::set('before', $DOM);
    
    return self::$cache[$key] = $DOM;
  }
  
  
  public  $info  = [];
  private $xpath = null,
          $props = [ 'preserveWhiteSpace' => false, 'formatOutput' => true, 'encoding' => 'UTF-8'];
  
  
  public function  __construct(string $xml, array $props = [])
  {
    parent::__construct('1.0', 'UTF-8');

    foreach (( $props + $this->props ) as $p => $value) $this->{$p} = $value;
    foreach (['Element','Text','Attr'] as       $c    ) $this->registerNodeClass("DOM{$c}", $c);
    
    if (! $this->loadXML($xml, LIBXML_COMPACT)) throw new ParseError('DOM Parse Error');
    
    $this->xpath = new DOMXpath($this);
  }
  
  public function __toString() {
    $prefix = $this->documentElement->nodeName == 'html' ? "<!DOCTYPE html>\n" : '';
    return $prefix . $this->saveXML($this->documentElement);
  }
  
  
  
  public function find($exp, ?DOMNode $context = null): DOMNodelist
  {
    if (! $result = $this->xpath->query($exp, $context))
      throw new Exception("Malformed predicate: {$exp}", 500);
    
    return $result;
  }
    
  public function select($exp, ?DOMNode $context = null) {
    return $this->find($exp, $context)[0] ?? null; 
  }
  
  public function evaluate(string $exp, ?DOMNode $context = null) {
    return $this->xpath->evaluate("string({$exp})", $context);
  }
  
  public function save($path) {
    return file_put_contents($path, $this->saveXML(), LOCK_EX);
  }
}


class Element extends DOMElement implements ArrayAccess {
  use DOMtextUtility;
  
  public function __construct($name, $value = null) {
    parent::__construct($name);
    if ($value) $this($value);
  }
  
  public function find(string $path) {
    return $this->ownerDocument->find($path, $this);
  }
  
  public function select(string $path) {
    return $this->ownerDocument->select($path, $this);
  }
  
  public function export(): string {
    return $this->ownerDocument->saveXML($this);
  }
  
  public function adopt(Element $element)
  {
    if ($element->ownerDocument != $this->ownerDocument)
      $element = $this->ownerDocument->importNode($element, true);
    while($node = $element->firstChild)
      $this->appendChild($node);
  }

  public function offsetExists($key) {
    return $this->find($key)->count() > 0;
  }

  public function offsetGet($key, $create = false, $index = 0)
  {
    if (($nodes = $this->find($key)) && ($nodes->length > $index))
      return new Data($nodes);
    else if ($create)
      return $this->appendChild(($key[0] == '@') ? new Attr(substr($key, 1)) : new Element($key));
    else 
      throw new UnexpectedValueException("{$this->parentNode->nodeName} does not have {$key}");
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
  
  public function __toString() {
    $out = [];
    foreach ($this->childNodes as $node) $out[] = $node->nodeValue;
    return join(' ', $out);
  }
}

class Text extends DOMText {
  use DOMtextUtility; 
  public function __construct(string $input, ...$args) {
    parent::__construct($args ? vsprintf($input, $args) : $input);
  }
}
class Attr extends DOMAttr {
  use DOMtextUtility;
  public function remove() {
    return ($elem = $this->ownerElement) ? $elem->removeAttribute($this->nodeName) : null;
  }
}

trait DOMtextUtility
{
  public function __invoke($input): self
  {
    $this->nodeValue = '';
    if ($input instanceof DOMNode) $this->appendChild($input);
    else $this->nodeValue = htmlspecialchars($input, ENT_XHTML, 'UTF-8', false);
    return $this;
  }
  
  public function __toString(): string {
    return $this->nodeValue;
  }
}

/**
 * Data stuff
 *
**/

trait Registry {
  public $data = [];
  public function __set($key, $value) {
    $this->data[$key] = $value;
  }
  public function __get($key) {
    return $this->data[$key] ?? null;
  }
  public function merge(array $data) {
    return $this->data = array_merge($this->data, $data);
  }
}

class Data extends ArrayIterator
{
  public $length = 0;
  private $maps = [];
    
  static public function fetch($namespace, $data, $wedge = '.')
  {
    if (is_array($namespace)) {
      while ($key = array_shift($namespace)) {
        $data = $data[$key] ?? null;
        if (is_callable($data) && ! $data instanceof Element) {
          return $data(...$namespace);
        }

      }
      return $data;
    }
    return $data[$namespace] ?? self::fetch(explode($wedge, $namespace), $data);
  }
    
  
  static public function apply(iterable $data, callable $callback): self {
    return (new self($data))->map($callback);
  }
  
  public function __construct(iterable $data){
    if (! is_array($data))
      $data = iterator_to_array($data);
    parent::__construct($data);
    $this->length = count($data);
  }
  
  public function current() {
    $current = parent::current();
    foreach ($this->maps as $callback) $current = $callback($current);
    return $current;
  }
  
  public function map(callable $callback): self {
    $this->maps[] = $callback;
    return $this;
  }

  public function sort($callback): self
  {
    if (is_callable($callback)) {
      if (!empty($this->maps)) return (new Data ($this))->sort($callback);

      $this->uasort($callback);
    }
    return $this;
  }
  
  public function filter(callable $callback) {
    return new CallbackFilterIterator($this, $callback);
  }

  public function limit($start, $length = -1) {
    $start-= 1;
    $start = ($length > 0 ? $start * $length : $start);
    $limit = new LimitIterator($this, $start, $length);
    $limit->length = $this->length;
    return $limit;
  }
  
  public function __toString() {
    return (string) $this->current();
  }
}

/**
 * Model | typ. used to model DOM accessable data with CRUD-able interfaces
 *
**/

abstract class Model implements ArrayAccess {
  protected $context;
  
  static public function FACTORY(...$args)
  {
    return print_r($args);
  }

  public function __construct(Element $context) { 
    $this->context = $context;
  }
  
  public function collect(string $expression) {
    return Data::apply($this->context->find($expression), fn($item) => new static($item));
  }
      
  public function offsetExists($key) {
    return isset($this->context[$key]) || method_exists($this, "get{$key}");
  }

  public function offsetGet($key)
  {
    if (property_exists($this, $key)) return $this->{$key};

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
