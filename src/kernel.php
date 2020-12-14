<?php

set_include_path(__DIR__);
libxml_use_internal_errors(true);
spl_autoload_register();




interface routable
{
  public function __invoke($template);
  public function reject(int $reason, $info):Exception;
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
    if (! $route = self::$paths[$response->route] ?? false) {
      throw $response->reject(404, self::$paths);
    }

    return $response->compose($route->fulfill($response), $route->path == Route::INDEX);
  }

  static public function set($path, ?callable $callback = null, array $config = [])
  {
    $path     = strtolower(trim($path, '/'));
    $instance = self::$paths[$path] ??= new self($path, $config['publish'] ?? 0);
    $instance->info += $config;
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
  static public function __callStatic($action, $arguments)
  {
    return self::set($action, ...$arguments);
  }

  static public function gather(array $files)
  {
    // this could be tallied on `set` so that it doesn't have to be mapped
    $dynamic = array_map(fn($obj) => join($obj->info) . $obj->path, self::$paths);
    $static  = array_map('filemtime', $files);
    $stash   = sys_get_temp_dir() . '/' . md5(join($dynamic+$static));

    if (file_exists($stash)) {
      $routes = json_decode(file_get_contents($stash), true);
      foreach ($routes as $path => $info)
        if (isset($info['file']))
          self::set($path, null, $info);

    } else {

      foreach(Data::apply($files, 'Document::open') as $DOM) {
        $path = $DOM->info['route'] ??= $DOM->info['file']['filename'];
        self::set($path, null, $DOM->info);
      }

      uasort(self::$paths, fn($A, $B) => ($A->publish) <=> ($B->publish));

      $routes = array_map(fn($R) => $R->info, self::$paths);
      file_put_contents($stash, json_encode($routes));
    }

    return array_filter($routes, fn($route) => $route['publish'] ?? false);
  }


  /**** Instance Properties and Methods **************************************/

  private $handle = null, $template = null, $exception = null;

  public $path, $publish, $info = [];

  private function __construct(string $path, int $publish = 0) {
    $this->path    = $path;
    $this->publish = $publish;
  }

  public function then(callable $handle): self {
    $this->handle[] = $handle;
    return $this;
  }

  public function catch(callable $handle)
  {
    $this->exception = $handle;
  }

  public function fulfill(routable $response, $out = null, int $i = 0) {

    if ($this->handle !== null) {
      $out = $response->params;
      try {
        do
          $out = $this->handle[$i++]->call($response, ...(is_array($out) ? $out : [$out]));
        while (isset($this->handle[$i]) && ! $response->fulfilled);

      } catch (Status $e) {
        if ($this->exception)
          $out = call_user_func($this->exception, $e);
        else
          throw $e;
      }
    }

    $response->fulfilled = true;
    $response->layout  ??= $this->info['layout'] ?? self::$paths[self::INDEX]->template;
    $response->render  ??= $this->info['render'] ?? null;

    return $out ?? $response($this->template);
  }

}

/**
* Description
*/
class Status extends Exception {
  const REASON = [
    401 => 'Unauthorized',
    404 => 'Not Found',
  ];
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
    'vtt'  => 'text/vtt',
    'srt'  => 'text/srt',
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
    $this->url   = $this->info['url'] = $this->local ? $this->info['scheme'].'://' . realpath($this->uri) : $this->uri;
    $this->mime  = self::MIME[$this->type] ?? 'text/plain';
    $this->id    = md5($this->url);
    if ($content) $this->setBody($content);
  }


  static public function load($path)
  {

    $instance = new static((strncmp($path, '.', 1) ? $path : __DIR__.'/'.$path));

    if (! $content = file_get_contents($instance->url)) throw new Error('Bad path: ' . print_r($instance->info, true));

    return $instance->setBody($content);
  }

  public function setBody($content): File {
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
    return (string)$this->body;
  }
}


/**
 * Request | This is used to configure an variable request (like a web server) via the constructor
 *
**/

class Request
{
  const REGEX = '/^((?>[^.]*).*?)(?:\.([a-z]\w{1,4}))?$/i';
  const TYPE  = 'html';

  public $uri, $method, $data = '', $headers = [];

  public function __construct(array $headers)
  {
    $uri = parse_url(urldecode($headers['REQUEST_URI']));
    $this->method  = $headers['REQUEST_METHOD'] ?? 'GET';
    $this->headers = $headers;

    preg_match(self::REGEX, trim($uri['path'], '/ .'), $match, PREG_UNMATCHED_AS_NULL);

    $this->uri   = $match[0] ?: $headers['REQUEST_URI'];
    $this->route = $match[1] ?: Route::INDEX;
    $this->type  = $match[2] ?: self::TYPE;
    $this->mime  = $headers['CONTENT_TYPE'] ?? File::MIME[$this->type] ?? File::MIME[self::TYPE];

    // can be POST, PUT or PATCH, which all contain a body
    if ($this->method[0] === 'P' && ($headers['CONTENT_LENGTH'] ?? 0) > 0)
      $this->data = file_get_contents('php://input');
  }

  static public function GET($uri, array $data = [], array $headers = []): Response
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

  public $route, $params, $document, $request, $basic, $headers = [], $fulfilled = false, $layout = null, $render = null;

  public function __construct(Request $request, array $data = [])
  {
    parent::__construct($request->uri);
    $this->merge($data);
    $this->request = $request;
    $this->params  = explode('/', $request->route);
    $this->route   = strtolower(array_shift($this->params));
    $this->id      = md5(join($request->headers));
    $this->basic   = $request->headers['HTTP_YIELD'] ?? ($request->mime != 'text/html');
  }

  public function setBody($content):File {
    $this->document = $content instanceof Element
                    ? new Document($content->ownerDocument->saveXML($content))
                    : $content;
    return parent::setBody((string)$content);
  }

  // string|Document $template
  public function yield(string $key, $source) {
    Template::set($key, $source instanceof Document ? $source : Document::open($source));
  }

  public function __invoke($path) {
    // when here, either no callback specified OR the callback returned void
    return $path ? Document::open($path) : new Document("<p>\u{26A0} /{$this->route}</p>");
  }

  public function reject(int $reason, $info = null): Exception {
    return new Status($this->route . ' ' . Status::REASON[$reason], $reason);
  }

  public function header($resource, $header)
  {
    if (! empty(trim($header))) {
      $split = strpos($header, ':') ?: 0;
      $key   = $split ? strtolower(substr($header, 0, $split)) : 'status';
      $this->headers[$key] = trim($split ? substr($header, $split+1) : $header);
    }

    return strlen($header); // required by the cURL (see @HTTP class, cURL documentation)
  }

  public function compose($payload, bool $is_index)
  {
    // if payload is not a DOM component or proper request, no processing to do
    if (! $payload instanceof DOMNode || $this->id === null) return $this->setBody($payload);


    if ($this->basic) {
       //no layout needed, just use payload document
      $layout = new Template($payload);
    } else {
      // find main document to use as layout

      $layout = new Template(Document::open($payload->info['layout'] ?? $this->layout));

      // make sure we aren't putting the layout into itself
      if (! $is_index) {
        $this->render = $payload->info['render'] ?? $this->render;
        Template::set($this->id, $payload);
      }
    }

    // render and transform the document layout
    $output = $layout->render(['route' => $this->route] + $payload->info + $this->data, $this->basic ? true : $this->id);
    $output = Render::transform($output, $this->render);

    // convert if request type is not markup
    return $this->setBody(Parser::check($output, $this->request->type));
  }
}


/**
 * Controller | Usually extended, but can be instantiatied @see Route class.
 *
 * The premise of a controller is to A. call a method corresponding to the first parameter
 * or B) Load a file should a method not exist.
**/

interface Authorized {
  static public function check(ReflectionMethod $credentials);
}

abstract class Controller
{
  protected $response, $path, $name;

  public function GETindex() {
    $this->response->basic = true;
    return call_user_func($this->response, $this->response->layout);
  }

  final public function __set($key, $value) {
    return $this->response->{$key} = $value;
  }

  final public function __get($key) {
    return $this->response->{$key};
  }

  final public function yield($key, $value) {
    return $this->response->yield($key, $value);
  }

  public function call(routable $response, $action = 'index', ...$params)
  {
    $instance = $this instanceof Proxy ? new $this->proxy($response, ...$this->props) : $this;
    $instance->response = $response;
    $instance->action   = strtolower($action);
    return $instance($response->request->method . str_replace('-','_',$instance->action), $params);
  }

  protected function open(array $ns = []): Document
  {
    /*
      TODO use $this->request->uri instead of all the joins and pointless constructor stuff;
    */
    $path = strtolower(join('/',[$this->path, $this->action, ...$ns]));

    if (! $file = (glob($path.'.*')[0] ?? false))
      $file = $path . '/index.html';

    return Document::open($file);
  }


  final public function __invoke($action, $params) {

    if (! method_exists($this, $action))
      return $this->open($params);

    $method = new ReflectionMethod($this, $action);

    // authenticate user: if ok, override visibility in check
    if ($method->isPrivate())
      array_unshift($params, Model\Agency::check($method));

    return $method->invokeArgs($this, $params);
  }
}

Class Proxy Extends Controller {
  protected $proxy, $props;

  public function __construct(string $proxy, array $props = [])
  {
    $this->proxy = $proxy;
    $this->props = $props;
  }
}



/**
 * Redirect | use as a controller, or throw anywhere to get a redirect going
 *
**/

class Redirect extends Exception {
  const STATUS    = ['permanent' => 301, 'temporary' => 302, 'other' => 303];
  public $location = null;
  public $headers = [
    ['Cache-Control: no-store, no-cache, must-revalidate, max-age=0'],
    ['Cache-Control: post-check=0, pre-check=0', false],
    ['Pragma: no-cache'],
  ];

  public function __construct(string $location, $code = 'temporary') {
    $this->headers['location'] = ["Location: {$location}", false, self::STATUS[$code]];
  }

  public function call(routable $response, ...$path)
  {
    $this->headers['location'][0] = sprintf($this->headers['location'][0], join('/', $path));
    throw $this;
  }

  public function __invoke(...$path) {
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
  private static $yield = [];

  # Note, this has a DOMNode as typecase, but should be Document|Element in 8.0
  static public function set(string $key, DOMNode $stub = null) {
    self::$yield[$key] = $stub;
  }

  private $DOM, $slugs = [];

  public function __construct(DOMnode $node)
  {
    Render::set('before', $node);

    if ($node instanceof Element && $doc = $node->ownerDocument) {
      $this->DOM = new Document($doc->saveXML($node));
      $this->DOM->info = $doc->info ?? null;
    } else {
      $this->DOM = $node;
    }
  }

  private function insert(iterable $stubs, $data, bool $cleanup = true)
  {
    foreach ($stubs as [$cmd, $path, $xpath, $context]) {

      if (strpos($path, '*') !== false) {
        $this->insert(Data::apply(glob($path), fn($path) => [$cmd, $path, $xpath, $context]), $data, false);
        continue;
      }

      $DOM = is_file($path) ? Document::open($path)
                            : Request::GET($path, $data)->document;

      $ref = $context->parentNode;

      foreach ($DOM->find($xpath) as $node) {
        if (! $node instanceof Text)
          $node = (new self($node))->render($data)->documentElement;

        $ref->insertBefore($this->DOM->importNode($node, true), $context);
      }

      if ($cleanup) $ref->removeChild($context);
    }
  }

  public function render($data = [], $ruid = null): Document
  {
    foreach ($this->getStubs('yield') as [$cmd, $prop, $exp, $ref]) {
      if ($DOM = self::$yield[$prop ?? $ruid] ?? null) {
        $context = ($exp !== '/') ? $ref : $ref->nextSibling;
        $node    = (new self($DOM))->render($data)->documentElement;
        $ref->parentNode->replaceChild($this->DOM->importNode($node, true), $context);
        if ($ref !== $context) $ref->parentNode->removeChild($ref);
      }
    }

    $this->insert($this->getStubs('insert'), $data);

    foreach ($this->getStubs('iterate') as [$cmd, $key, $exp, $ref]) {

      $template = new self($ref->nextSibling->remove());
      $template->getSlugs("[not(ancestor-or-self::*/preceding-sibling::comment()[starts-with(normalize-space(.), 'iterate')])]");

      $reset  = $template->DOM->documentElement->cloneNode(true);
      $invert = $exp !== '/';
      $slug   = $ref;

      foreach (Data::fetch($key, $data) ?? [] as $datum) {
        if ($import = $template->render($datum, $ruid ?? true)->documentElement) {
          $node = $this->DOM->importNode($import, true);
          $slug = $ref->parentNode->insertBefore($node, $invert ? $slug : $ref);
          $template->DOM->replaceChild($reset->cloneNode(true), $template->DOM->firstChild);
        }
      }

      $ref->parentNode->removeChild($ref);
    }

    if ($ruid) $this->parse($data);

    return $this->DOM;
  }

  private function parse($data)
  {
    $audit = [];
    foreach ($this->getSlugs() as $path => $slugs) {
      if ($node = $this->DOM->select($path)) {

        foreach ($slugs as [$key, $offset]) {
          $replacement = Data::fetch($key, $data);
          if ($replacement === null && $audit[] = $node) continue 2;
          $node->replace($replacement, ...$offset);
        }
      }
    }
    foreach ($audit as $node) $node->remove();
  }


  private function getStubs($key): iterable
  {
    $x = "comment()[starts-with(normalize-space(.), '{$key}')]";

    if ($key == 'iterate')
      $x .= "[not(ancestor-or-self::*/preceding-sibling::{$x})]";

    return Data::apply($this->DOM->find("//{$x}"), fn($n) =>
      preg_split('/\b\s+/', trim($n->data), 3) + [1 => null, 2 => '/', 3 => $n]
    );
  }


  protected function getSlugs($skip = ''): iterable
  {
    if (empty($this->slugs)) {

      $xp = 'contains(.,"${")';

      foreach ( $this->DOM->find("//*[not(self::script or self::code)]{$skip}/text()[{$xp}]|//*{$skip}/@*[{$xp}]") as $node ) {
        $path = $node->getNodePath();
        $text = $node->textContent;

        $this->slugs[$path] ??= [];

        preg_match_all('/\$\{[^}]+\}+/i', $text, $match, PREG_OFFSET_CAPTURE);

        foreach (array_reverse($match[0]) as [$k, $i]) {

          $split = [mb_strlen(substr($text, 0, $i)), mb_strlen($k)];
          $key   = substr($text, $split[0]+2, $split[1] - 3);

          // Scope change, ie, ${${key}}. Skip and adjust offset starts of already-set slugs
          if ($key[0] == '$') {
            foreach($this->slugs[$path] as &$item) $item[1][0] -= 3;
            $node->replace($key, ...$split);
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
  static public function open($path, array $opt = [])
  {
    $key = is_string($path) ? $path : $path->id;

    if (self::$cache[$key] ?? false) return self::$cache[$key];

    $file = $path instanceof File ? $path : File::load($path);

    try {
      if (! $file->local)
        $file->body = preg_replace('/\sxmlns=[\"\'][^\"\']+[\"\'](*ACCEPT)/', '', $file->body);

      $DOM = (substr($file->mime, -2) == 'ml') ? new self($file->body, $opt) : Parser::load($file);
    } catch (ParseError $e) {
      $err = (object)libxml_get_errors()[0];
      $hint = substr(file($path)[$err->line-1], max($err->column - 10, 0), 20);
      throw new ErrorException($err->message . " in {$path}, around: '{$hint}'", 500, E_ERROR, realpath($path), $err->line, $e);
    }

    foreach ($DOM->find("/processing-instruction()") as $pi)
      $DOM->info[$pi->target] = trim($pi->data);

    $DOM->info['src']     = $path;
    $DOM->info['file']    = $file->info;
    $DOM->info['title'] ??= ucwords(str_replace('-', ' ', $DOM->info['file']['filename']));
    $DOM->info['size']    = $file->size;
    return self::$cache[$key] = $DOM;
  }


  public  $info  = [];
  private $xpath = null,
          $props = [ 'preserveWhiteSpace' => false, 'formatOutput' => true, 'encoding' => 'UTF-8'];


  public function  __construct(?string $xml = null, array $props = [])
  {
    parent::__construct('1.0', 'utf-8');

    foreach (( $props + $this->props ) as $p => $value) $this->{$p} = $value;
    foreach (['Element','Text','Attr'] as       $c    ) $this->registerNodeClass("DOM{$c}", $c);

    if ($xml && ! $this->loadXML($xml, LIBXML_COMPACT)) throw new ParseError('DOM Parse Error');

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

  public function map(string $exp, callable $callback, ?DOMNode $context = null) {
    return Data::apply($this->find($exp, $context), $callback);
  }

  public function save($path = null, $validate = false) {
    if ($validate && ! $this->validate()) {
      // todo develop routine for validation problems
      print_r(libxml_get_errors());
    }

    return file_put_contents($path ?? $this->info['src'], $this->saveXML(), LOCK_EX);
  }
}


class Element extends DOMElement implements ArrayAccess {
  use DOMtextUtility;
  public $info = [];
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

  public function map(string $exp, callable $callback) {
    return $this->ownerDocument->map($exp, $callback, $this);
  }

  public function adopt(DOMNode $element)
  {
    if ($element->ownerDocument != $this->ownerDocument)
      $element = $this->ownerDocument->importNode($element, true);
    while($node = $element->firstChild)
      $this->appendChild($node);
    return $this;
  }

  public function rename(Element $node)
  {
    return $node->adopt($this->parentNode->replaceChild($node, $this));
  }

  public function offsetExists($key) {
    return $this->find($key)->length > 0;
  }

  public function offsetGet($key, $create = false, $index = 0)
  {
    if (($nodes = $this->find($key)) && ($nodes->length > $index))
      return new Data($nodes);
    elseif ($create && $type = $key[0] == '@' ? 'Attr' : 'Element')
      return $this->appendChild(new $type(ltrim($key, '@')));

    return null;
  }

  public function offsetSet($key, $value) {
    return $this->offsetGet($key, true)($value);
  }

  public function offsetUnset($key) {
    foreach ($this[$key] as $node) $node->remove();
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

  public function remove() {
    return ($parent = $this->parentNode) ? $parent->remove() : null;
  }

  public function replace($data, int $start, int $length)
  {
    if ($data instanceof DOMNode) {
      $stub = $this->splitText($start)->splitText($length)->previousSibling;
      $this->parentNode->replaceChild($this->ownerDocument->importNode($data, true), $stub);
    } else {
      $this->replaceData($start, $length, $data);
    }


  }
}
class Attr extends DOMAttr {
  use DOMtextUtility;

  public function replace(string $data, int $start, int $length)
  {
    $this(substr_replace($this->value, $data, $start, $length));
  }

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
        $data = $data[$key] ?? $data->key ?? null;
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

  public function join(string $glue = '') {
    return join($glue, iterator_to_array($this));
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

  public function limit(int $start, int $length = -1) {
    $start-= 1;
    $start = $length > 0 ? $start * $length : $start;
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

abstract class Model implements ArrayAccess
{
  public const SOURCE = null;
  public const ID     = '//*[@id="%s"]';
  public $context, $initialized;

  protected function initialize($context):bool
  {
    return true;
  }

  /**
   * Factory method that returs a modeled collection from a query
   *
   * @param string $model
   * @param string $query
   * @return void
   * @author Brendan Metzger
   */
  static public function __callStatic(string $model, $query)
  {
    $model = "Model\\{$model}";
    return Document::open($model::SOURCE)->map(join('|', $query), fn($node) => new $model($node));
  }


  static public function FACTORY($model, $method, ...$args)
  {
    $model = "\\model\\{$model}";
    return $model::$method(...$args);
  }

  /**
   * Create an instance by id
   *
   * This has some magic so it can be directly used from a template w/
   * Modelname::ID.2343.whatevermethod.whatevermethod
   *
   */
  static public function ID($id, ...$params) {
    if (static::SOURCE === null) {
      throw new Error("Model has not specified a data source", 500);
    }

    $context = Document::open(static::SOURCE)->select(sprintf(static::ID, $id));
    return array_reduce($params, fn($c, $k) => $c[$k], new static($context));
  }

  final public function __construct($context) {
    $this->context = $context;
  }

  public function offsetExists($key) {

    return isset($this->context[$key]) || method_exists($this, "get{$key}");
  }

  public function offsetGet($key)
  {
    if (property_exists($this, $key)) return $this->{$key};

    $initialized ??= $this->initialize($this->context);

    $method  = "get{$key}";
    return method_exists($this, $method) ? $this->{$method}($this->context) : $this->context[$key];
  }

  public function offSetSet($key, $value) {
    return $this->context[$key] = $value;
  }

  public function offsetUnset($key) {
    unset($this->context[$key]);
  }

  final public function __toString() {
    return $this->context['@id'];
  }
}
