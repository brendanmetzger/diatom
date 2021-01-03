<?php namespace Controller;

use Document, Model, Auth, Status;

class System extends \Controller
{
  use \Configured;

  final static public function load($flag) {
    if (self::config($flag)) {
      \Route::system(new \Controller('Controller\System'));

      \Render::set('before', function($node) {
        [$doc, $context] = $node instanceof Document ? [$node, $node->documentElement] : [$node->ownerDocument, $node];

        foreach ($context->find('.//*[not(@data-path or self::script or self::style) and text() and not(ancestor::*/text())]') as $node)
          $node->setAttribute('data-path', $node->getNodePath());

        if ($path = $doc->info['src'] ?? false)
          $context->setAttribute('data-doc', $path);
      });

      \Render::set('after', function($context) {
        if ($body = $context->select('//body')) {
          $body->setAttribute('data-prompt', \Request::config('mode'));
          $body->setAttribute('data-root', realpath('.'));
          $body->appendChild(new \Element('script'))->setAttribute('src', '/ux/js/edit.js');
        }
      });
    }
  }

  public function GETerror(int $code = 404)
  {
    $this->error ??= [
      'code'    => $code,
      'message' => 'Resource Not Found',
    ];

    return Document::open("system/{$code}.html");
  }

  public function GETauth()
  {
    // these variables are the ones we get from the oAuth requst
    $_GET += ['code' => '', 'state' => ''];
    // the verified token contains the original request uri
    if ($verified = Auth\Token::verify($_GET['state'])) {

      $token = Auth\Token::generate($_GET['code']);
      $keys  = \HTTP::GET('https://api.github.com/user/emails', $token->headers('json'));
      $value = join(array_column($keys->data, 'email'));

      if ($user = Model\User::ID($value)) {
        $token->save($user);
        throw $this->response->state(Status::REDIRECT, $verified);
      }
      throw $this->response->state(Status::UNAUTHORIZED);
    }

    // Logout I s'pose
    if ($this->response->request->authorization(Auth\Token::NAME)) {
      Auth\Token::invalidate();
      throw new $this->response->state(Status::REDIRECT, '/');
    }
    throw $this->response->state(Status::NOT_FOUND);
  }


  public function GETraw($file) {
    return \Parser::check(Document::open(base64_decode($file)), $this->type);
  }


  public function PUTupdate()
  {
    $filepath = $this->request->resource->documentElement->getAttribute('data-doc');
    $original = Document::open($filepath);

    $xp = '//*[contains(.,"${") and not(*) and not(self::script or self::code)]|//*/@*[contains(.,"${")]';

    $cache = []; // cache template literals to swap back in ${} after edits
    foreach ($original->find($xp) as $template)
      $cache[$template->getNodePath()] = $template->nodeValue;

    foreach ($this->request->resource->find('//*[@data-path]') as $node)
      if ($context = $original->select($node['@data-path']))
        $context->parentNode->replaceChild($original->importNode($node, true), $context);

    foreach ($cache as $path => $value)
      $original->select($path)($value);


    $copy = new Document($original->saveXML());

    foreach($copy->find('//*[@data-path or @data-doc]') as $node)
      array_map([$node, 'removeAttribute'], ['data-doc', 'data-path', 'contenteditable', 'spellcheck']);

    $output = \Parser::check($copy, $original->info['file']['extension']);
    if ($output instanceof Document) {
      $output = $output->saveXML();
    }
    if (file_put_contents($filepath, $output))
      return $output;
  }

}
