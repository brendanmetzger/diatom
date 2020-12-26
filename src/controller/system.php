<?php namespace Controller;

use Document, Model, Auth;

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
        throw new \Redirect($verified);
      }
      throw $this->response->reject(401);
    }

    // Logout I s'pose
    if ($this->response->request->authorization(Auth\Token::NAME)) {
      Auth\Token::invalidate();
      throw new \Redirect('/');
    }
    throw $this->response->reject(404);
  }


  public function GETraw($file) {
    return \Parser::check(Document::open(base64_decode($file)), $this->type);
  }


  public function PUTupdate()
  {
    // TODO, parse request type so don't have to create document, and this would strip out xmlns perhaps
    $updated  = new Document(preg_replace('/\sxmlns=[\"\'][^\"\']+[\"\'](*ACCEPT)/', '', $this->response->request->body));

    $filepath = $updated->documentElement->getAttribute('data-doc');
    $original = Document::open($filepath);

    $xp = '//*[contains(.,"${") and not(*) and not(self::script or self::code)]|//*/@*[contains(.,"${")]';

    $cache = [];
    foreach ( $original->find($xp) as $template)
      $cache[$template->getNodePath()] = $template->nodeValue;


    foreach ($updated->find('//*[@data-path]') as $node) {
      if ($context = $original->select($node['@data-path'])) {
        // cache literals to swap back in ${}..
        $context->parentNode->replaceChild($original->importNode($node, true), $context);
      }
    }


    foreach ($cache as $path => $value)
      $original->select($path)($value);


    $copy = new Document($original->saveXML());

    foreach($copy->find('//*[@data-path or @data-doc]') as $node)
      array_map([$node, 'removeAttribute'], ['data-doc', 'data-path', 'contenteditable', 'spellcheck']);

    print_r($original->info);

    if (file_put_contents($filepath, \Parser::check($copy, $original->info['file']['extension'])))
      return $updated;
  }

}
