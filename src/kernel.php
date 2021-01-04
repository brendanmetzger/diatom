<?php

set_include_path(__DIR__);
libxml_use_internal_errors(true);
spl_autoload_register();


interface routable ####################################################################### ROUTABLE
{
  public function output($instruction = null) : stringable | string;
  public function status(int $reason, $info)  : Exception;
  public function compose($payload)           : self;
}




trait Configured ####################################################################### CONFIGURED
{
  static private $config;
  static public function config($key = null) {
    self::$config ??= App::config(strtolower(self::class));
    return self::$config[$key] ?? self::$config;
  }
}




trait Registry ########################################################################### REGISTRY
{
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




trait DOMtextUtility ############################################################### DOMtextUtility
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




class App #################################################################################### APP
{ use Configured;
  static public function config($class) {
    return (self::$config ??= parse_ini_file('../data/config', true))[$class] ?? [];
  }
}




class Route ################################################################################# ROUTE
{ use Configured;

  static private $paths = [], $id = 0, $prepared = false;
  static public function delegate(routable $router): routable
  {
    $paths   = self::prepare($router);
    $payload = ($paths[$router->route] ?? throw $router->status(404, $paths))->fulfill($router);
    return $router->compose($payload);
  }

  static public function set($path, callable $callback, array $config = []): Route
  {
    $path  = strtolower(trim($path, '/'));
    $route = self::$paths[$path] ??= new self($path, $config['publish'] ?? 0);
    $route->info += $config;
    $route->info['title'] ??= ucwords(preg_replace('/[_-]+/', ' ', $route->path));
    // fuzzy checksum to aid caching
    self::$id ^= crc32($path . join($config));

    return $route->then($callback);
  }

  // WARNING: an alias for the above, can potentially collide with an existing Route method
  static public function __callStatic($action, $arguments): Route {
    return self::set($action, ...$arguments);
  }

  static private function prepare(routable $router): iterable
  {
    static $prepared = false;
    if (! $prepared) {

      $root  = self::config('directory');
      $scan  = scandir($root, SCANDIR_SORT_NONE);
      $key   = array_reduce($scan, fn($c,$i) => $c^filemtime($root.'/'.$i), self::$id);
      $stash = sprintf('%s/%X.json', sys_get_temp_dir(), $key);
      $route = Closure::fromCallable([$router, 'output']);

      if (is_file($stash)) {
        $routes = json_decode(file_get_contents($stash), true);
        foreach ($routes as $path => $info) {
          if      (key_exists('controller',$info)) self::set($path, new Controller($info['controller']?:null), $info);
          else if (isset($info['src'])) self::set($path, $route, $info);
        }

      } else {
        // scan for files (direct routes)
        foreach(Data::apply(array_filter(array_map(fn($f) => $root.'/'.$f, $scan), 'is_file'), 'Document::open') as $DOM) {
          $info = $DOM->info;
          $info['route'] ??= $info['file']['filename'];
          unset($info['file']);
          self::set($info['route'], $route, $info);
        }

        // scan for directories (controllers)
        foreach (array_filter($scan, fn($f) => is_dir($root.'/'.$f) && $f[-1] != '.') as $name) {
          $ctrl = new Controller(class_exists("controller\\{$name}", true) ? "controller\\{$name}" : null);
          $info = ['controller' => $ctrl->name, 'publish' => $ctrl::PUBLISH, 'src' => glob("{$root}/{$name}/index.*")[0] ?? null];
          self::set($name, $ctrl, $info);
        }

        uasort(self::$paths, fn($A, $B) => ($A->publish) <=> ($B->publish));
        $routes =  array_map(fn($R) => $R->info, self::$paths);
        file_put_contents($stash, json_encode($routes));
      }
      $router->data[$root] = array_filter($routes, fn($route) => $route['publish'] ?? false);
      $prepared = true;
    }

    return self::$paths;
  }

  private $handle = null, $exception = null;
  public $info = [], $index = false;

  private function __construct(public string $path, public int $publish = 0) {
    $this->info['route'] = $path;
    $this->index = $path == self::config('default');
  }

  public function __toString() {
    return $this->path;
  }

  public function then(callable $handle): self {
    $this->handle[] = $handle;
    return $this;
  }

  public function catch(callable $handle): void {
    $this->exception = $handle;
  }

  public function fulfill(routable $response, $out = null, int $i = 0)
  {
    $out = $response->params;
    $response->template = $this->info['src'] ?? null;
    try {
      do {
        $out = $this->handle[$i++]->call($response, ...(is_array($out) ? $out : [$out]));
      } while (isset($this->handle[$i]) && $response->request->status->getCode() < 100);
    } catch (Status $e) {
      $out = $this->exception?->call($response, $e) ?? throw $e;
    }

    $response->status(Status::SUCCESS);

    $response->layout  ??= $this->info['layout'] ?? self::$paths[self::config('default')]?->info['src'];
    $response->render  ??= $this->info['render'] ?? null;

    return $out ?? $response->output();
  }
}




class Status extends Exception ###############################################################
{
  const SUCCESS = 200, CREATED = 201, REDIRECT = 307, MOVED = 308, UNAUTHORIZED = 401, NOT_FOUND = 404, ERROR = 500;

  public function __construct(public Request $request, string $file)
  {
    parent::__construct($request->origin);
    if (is_file($this->file = $file))
      $this->code = 201;
  }

  public function report(int $code, $message = ''): self
  {
    $this->message = $message;
    $this->code    = $code;
    return $this;
  }
}




class File ################################################################################### FILE
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

  public $uri, $info, $mime, $local = true, $size = 0, $body = null, $resource;

  public function __construct(public string $url, public $type = 'txt')
  {
    $this->info  = parse_url($url);
    $this->uri   = $this->info['path'] ?? '/';
    $this->info += pathinfo($this->uri);
    $this->type  = strtolower($this->info['extension'] ??= substr($this->uri .= '.' . $type, 1+~strlen($type)));
    $this->info['scheme'] ??= str_ends_with($this->type, 'gz') ? 'compress.zlib' : 'file';
    $this->local = ! str_contains($this->info['scheme'], 'http');
    $this->url   = $this->local ? $this->info['scheme'].'://' . ($this->uri[0] == '/' ? $this->uri : realpath($this->uri)) : $url;
    $this->mime  = self::MIME[$this->type] ?? 'text/plain';
  }

  static public function load($path): File
  {
    $instance = new self((strncmp($path, '.', 1) ? $path : __DIR__.'/'.$path));
    $content  = file_get_contents($instance->url) ?: throw new Error('Bad path: ' . print_r($instance->info, true));
    return $instance->setBody($content);
  }

  public function setBody(string|stringable $content, ?string $mime = null): File
  {
    if ($content instanceof DOMNode && !$mime) {
      $this->resource = $content instanceof Document ? $content : new Document($content->ownerDocument->saveXML($content));
    } else if ($mime) {
      $type = explode(';', $mime)[0];
      if (preg_match('/application\/\S*json/', $type))
        $this->resource = json_decode($content, true);
      else if (substr($type, -2) == 'ml')
        $this->resource = new Document(preg_replace('/\sxmlns=[\"\'][^\"\']+[\"\'](*ACCEPT)/', '', $content));
    }

    $this->size = strlen($this->body = (string)$content);
    return $this;
  }

  public function save(bool $overwrite = false): bool
  {
    return ! is_file($this->uri) || $overwrite
           ? file_put_contents($this->uri, $this->body, LOCK_EX)
           : throw new Error("File cannot be written", 1);
  }

  public function verify(string $hash, string $algo = 'md5'): bool {
    return hash($algo, $this->body) === trim($hash, '"');
  }

  public function __toString() {
    return (string) $this->body;
  }
}




class Request extends File ################################################################ REQUEST
{ use Configured;

  public $origin, $method, $status;

  public function __construct(?string $host = null, public array $headers, public $root = '')
  {
    $this->method = $headers['REQUEST_METHOD'] ?? 'GET';
    $this->origin = rtrim($headers['REQUEST_URI'], '/') ?: '/' . Route::config('default');

    parent::__construct(($host ?? self::config('host')) . $this->origin, type: 'html');

    $this->status = new Status($this, $root.$this->uri);

    if ($this->method[0] === 'P' && ($headers['CONTENT_LENGTH'] ?? 0) > 0)
      $this->setBody(file_get_contents('php://input'), $headers['CONTENT_TYPE'] ?? $this->mime);

    if ($this->status->getCode())
      throw $this->status;
  }

  static public function GET($uri, array $data = [], array $headers = [], $yield = true): Response
  {
    $request = new Request('/', array_merge($headers, [
      'REQUEST_URI'    => $uri,
      'REQUEST_METHOD' => 'GET',
      'CONTENT_TYPE'   => $headers['content-type'] ?? null,
      'HTTP_YIELD'     => $yield,
    ]), realpath('.'));


    return Route::delegate(new Response($request, $data));
  }

  public function authorization($key): string {
    return $_COOKIE[$key] ?? $this->headers['Authorization'] ?? '';
  }
}




class Response implements routable ####################################################### RESPONSE
{ use Registry;

  public $route, $id, $params, $document, $basic, $layout = null, $template, $render = null;

  public function __construct(public Request $request, array $data = [], public array $headers = [])
  {
    $this->merge($data);
    $this->header('Content-Type', $this->request->mime);
    $this->params = explode('/', substr($request->uri, 1, ~strlen($request->type)));
    $this->route  = strtolower(array_shift($this->params));
    $this->id     = md5(join($request->headers));
    $this->basic  = $request->headers['HTTP_YIELD'] ?? ($request->type != 'html');
  }

  // when here, either no callback specified OR the callback returned void
  public function output($path = null): stringable | string {
    return Document::open($path ?? $this->template ?? throw $this->status(404));
  }

  public function yield(string $key, string|Document $source): void {
    Template::set($key, $source instanceof Document ? $source : Document::open($source));
  }

  public function status(int $reason, $info = null): Exception {
    return $this->request->status->report($reason, $info ?? $this->request->origin);
  }

  public function header($key, $header): int
  {
    if ($key instanceof CurlHandle && ! empty(trim($header))) {
      [$key, $value] = preg_split('/:? /', $header, 2);
      $this->headers[strtolower($key)] = trim($value);
    } else {
      $this->headers[$key] = $header;
    }

    return strlen($header); // required by the cURL (see @HTTP class + cURL documentation)
  }

  public function compose($payload): self
  {
    if ($payload instanceof DOMNode && $this->id) {

      if ($this->basic) {
         //no layout needed, just use payload document
        $layout = new Template($payload);

      } else {
        // find main document to use as layout
        $layout = new Template(Document::open($payload->info['layout'] ?? $this->layout));

        // make sure we aren't putting the layout into itself
        if ($this->route != Route::config('default')) {
          $this->render = $payload->info['render'] ?? $this->render;
          Template::set($this->id, $payload);
        }
      }

      // render and transform the document layout
      $data    = ['route' => $this->route] + $payload->info + $this->data + Request::config();
      $payload = Render::transform($layout->render($data, $this->basic ?: $this->id), $this->render);

      // convert if request type is not markup
      $payload = Parser::check($payload, $this->request->type);
    }

    $this->request->setBody($payload);
    return $this;
  }

  public function __toString() {
    // write headers
    http_response_code($this->request->status->getCode());
    $this->header('Content-Length', $this->request->size);
    foreach ($this->headers as $key => $value)
      header("{$key}: {$value}");
    return (string) $this->request->body;
  }
}




class Controller ####################################################################### CONTROLLER
{
  const PUBLISH = 0;
  protected $response;

  public function __construct(public $name = null, protected array $params = []) {}

  final public function __invoke($action, $params)
  {
    if (! method_exists($this, $action)) {
      $fuzzy = preg_replace('/(x|ht)ml$/', '*', $this->response->request->uri);
      $path  = glob(Route::config('directory') . $fuzzy)[0] ?? null;
      return $this->response->output($path);
    }

    $method = new ReflectionMethod($this, $action);

    // authenticate user: if ok, override visibility in check
    if ($method->isProtected()) {
      // A token is found + verified, or the Authorization process will be thrown
      $digest = $this->request->authorization(Auth\Token::NAME);
      $token  = Auth\Token::verify($digest) ?? Auth\Token::authorize($this->request);
      $method->setAccessible(true);
      array_unshift($params, Model\User::ID($token));
    }
    return $method->invokeArgs($this, $params);
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
    $instance = $this->name ? new $this->name($response, ...$this->params) : $this;
    $instance->response = $response;
    $instance->action   = strtolower($action);

    return $instance($response->request->method . str_replace('-','_',$instance->action), $params);
  }

}




class Template ########################################################################### TEMPLATE
{
  static private $yield = [];
  static public function set(string $key, Document|Element $stub): void {
    self::$yield[$key] = $stub;
  }

  private $DOM, $slugs = [];

  public function __construct(Document|Element $node)
  {
    Render::set('before', $node);

    if ($node instanceof Element && $doc = $node->ownerDocument) {
      $this->DOM = new Document($doc->saveXML($node));
      $this->DOM->info = $doc->info ?? null;
    } else {
      $this->DOM = $node;
    }
  }

  private function insert(iterable $stubs, $data, bool $cleanup = true): void
  {
    foreach ($stubs as [$cmd, $path, $xpath, $context]) {

      if (str_contains($path, '*')) {
        $this->insert(Data::apply(glob($path), fn($path) => [$cmd, $path, $xpath, $context]), $data, false);
        continue;
      }

      $DOM = is_file($path) ? Document::open($path) : Request::GET($path, $data)->request->resource;
      $ref = $context->parentNode;

      foreach ($DOM->find($xpath) as $node) {
        if (! $node instanceof Text)
          $node = (new self($node))->render($data)->documentElement;

        $ref->insertBefore($this->DOM->importNode($node, true), $context);
      }

      if ($cleanup) $ref->removeChild($context);
    }
  }

  public function render(iterable $data = [], $ruid = null): Document
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

      $template = new self($ref->nextElementSibling->parentNode->removeChild($ref->nextElementSibling));
      $template->getSlugs("[not(ancestor-or-self::*/preceding-sibling::comment()[starts-with(normalize-space(.), 'iterate')])]");

      $reset  = $template->DOM->documentElement->cloneNode(true);
      $invert = $exp !== '/' ;
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

  private function parse($data): void
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

  private function getStubs(string $key): iterable
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

        preg_match_all('/\$\{([^}]+)\}/i', $text, $match, PREG_OFFSET_CAPTURE|PREG_SET_ORDER);

        foreach (array_reverse($match) as [$full, $key]) {
          $split = [mb_strlen(substr($text, 0, $full[1]), 'utf-8'), mb_strlen($full[0])];
          if ($key[0][0] == '$') {
            foreach($this->slugs[$path] as &$item) $item[1][0] -= 3;
            $node->replace($key[0], ...$split);
            continue;
          }
          $this->slugs[$path][] = [$key[0], $split];
        }
      }
    }
    return $this->slugs;
  }
}




class Document extends DOMDocument ####################################################### DOCUMENT
{
  static private $cache = [];
  static public function open(string|File $path, array $opt = []): Document
  {
    $key = is_string($path) ? $path : $path->url;

    if (self::$cache[$key] ?? false) return self::$cache[$key];

    $file = $path instanceof File ? $path : File::load($path);

    try {
      $DOM = (substr($file->mime, -2) == 'ml') ? new self($file->body, $opt) : Parser::load($file);
    } catch (ParseError $e) {
      $err = (object)libxml_get_errors()[0];
      throw new ErrorException($err->message, 500, E_ERROR, realpath($path), $err->line, $e);
    }

    foreach ($DOM->find("/processing-instruction()") as $pi)
      $DOM->info[$pi->target] = trim($pi->data);

    $DOM->info['src']     = $file->uri;
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

  public function find($exp, ?DOMNode $context = null): DOMNodelist {
    return $this->xpath->query($exp, $context) ?: throw new Exception("Malformed predicate: {$exp}", 500);
  }

  public function select($exp, ?DOMNode $context = null): ?DOMNode {
    return $this->find($exp, $context)[0] ?? null;
  }

  public function evaluate(string $exp, ?DOMNode $context = null): string {
    return $this->xpath->evaluate("string({$exp})", $context);
  }

  public function map(string $exp, callable $callback, ?DOMNode $context = null) {
    return Data::apply($this->find($exp, $context), $callback);
  }

  public function save($path = null, $validate = false): bool
  {
    if ($validate && ! $this->validate()) {
      // todo develop routine for validation problems
      throw new Exception(print_r(libxml_get_errors(), true));
    }

    return file_put_contents($path ?? $this->info['src'], $this->saveXML(), LOCK_EX);
  }
}




class Element extends DOMElement implements ArrayAccess, JsonSerializable ################# ELEMENT
{ use DOMtextUtility;

  public $info = [];

  public function jsonSerialize() {
    return simplexml_import_dom($this);
  }

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

    // TODO: can now do $this->append(...$element->childNodes);
    while($node = $element->firstChild)
      $this->appendChild($node);
    return $this;
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

  public function __call($key, $args): DOMNode {
    return $this->offsetSet($key, ...$args);
  }

  public function __toString() {
    $out = [];
    foreach ($this->childNodes as $node) $out[] = $node->nodeValue;
    return join(' ', $out);
  }
}




class Text extends DOMText ################################################################### TEXT
{ use DOMtextUtility;

  public function __construct(string $input, ...$args) {
    parent::__construct($args ? vsprintf($input, $args) : $input);
  }

  public function remove(): void {
    $this->parentNode->remove();
  }

  public function replace($data, int $start, int $length)
  {
    if ($data instanceof DOMNode) {
      $stub = $this->splitText($start)->splitText($length)->previousSibling;
      // TODO: can now do $stub->replaceWith($this->ownerDocument->importNode($data, true));
      $this->parentNode->replaceChild($this->ownerDocument->importNode($data, true), $stub);
    } else {
      $this->replaceData($start, $length, $data);
    }
  }
}




class Attr extends DOMAttr ################################################################### Attr
{ use DOMtextUtility;

  public function replace(string $data, int $start, int $length) {
    $this(substr_replace($this->value, $data, $start, $length));
  }

  public function remove() {
    return ($elem = $this->ownerElement) ? $elem->removeAttribute($this->nodeName) : null;
  }
}




class Data extends ArrayIterator ############################################################# DATA
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

  public function __toString() {
    return (string) $this->current();
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

  public function limit(int $start, int $length = -1)
  {
    $start-= 1;
    $start = $length > 0 ? $start * $length : $start;
    $limit = new LimitIterator($this, $start, $length);
    $limit->length = $this->length;
    return $limit;
  }

}




abstract class Model implements ArrayAccess ################################################# MODEL
{
  const SOURCE = null;
  const IDPATH = '//*[@id="%s"]';
  const ID     = 'id';

  public $initialized;

  protected function initialize($context): bool {
    return true;
  }

  static public function __callStatic(string $model, $query)
  {
    $model = "Model\\{$model}";
    return Document::open($model::SOURCE)->map(join('|', $query), fn($node) => new $model($node));
  }

  // TODO: This can go eventually
  static public function FACTORY($model, $method, ...$args)
  {
    $model = "\\model\\{$model}";
    return $model::$method(...$args);
  }

  static public function ID($id, ...$params)
  {
    $filepath = static::SOURCE ?? throw new Error("No specified source for model data", 500);
    $context  = Document::open($filepath)->select(sprintf(static::IDPATH, $id));
    return array_reduce($params, fn($c, $k) => $c[$k], new static($context));
  }

  public function __construct(public DOMNode $context) {}

  public function offsetExists($key) {
    return isset($this->context[$key]) || method_exists($this, "get{$key}");
  }

  public function offsetGet($key)
  {
    if (property_exists($this, $key)) return $this->{$key};

    $initialized ??= $this->initialize($this->context);
    $method       = "get{$key}";

    return method_exists($this, $method) ? $this->{$method}($this->context) : $this->context[$key];
  }

  public function offSetSet($key, $value) {
    return $this->context[$key] = $value;
  }

  public function offsetUnset($key) {
    unset($this->context[$key]);
  }

  final public function __toString() {
    return $this->context->getAttribute(static::ID);
  }
}
