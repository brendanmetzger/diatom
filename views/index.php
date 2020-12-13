<?php define('CONFIG', parse_ini_file('../data/config', true));
      require_once '../src/kernel.php';

ini_set('xdebug.mode', 'coverage');

/**** ROUTING ****************************************************************/

if (CONFIG['data']['mode'] ?? false) {

Route::edit(new \Controller\Edit);

}


Route::promisy(function($message = 'add a param to address') {

  $this->message = strtolower($message);

  // visit  /promisy/blorf to see how fullfilled might work
  $this->fulfilled = ($this->message === 'blorf');

  return new Document('<main><h2>${message}</h2></main>');

})->then(function($payload){
  // otherwise document is not fullfilled, so the return value(s) of the
  // previous method become arguments to this method. This is useful for
  // caching (ie., Route::expensive(check)->then(do)->then(cache)
  // or authentication... ie, Route::path(authenticate)->then(do);
  $payload->documentElement->appendChild(new Element('h1', 'cool?'));

  // to see the final catch, uncomment
  // throw $this->reject(404);

  return $payload;

})->catch(function(Status $exception) {

  return new Document('<p>this cannot happen</p>');

});



Route::example(function($greeting = 'world') {

  $this->yield('sample', 'partials/table.md');

  $this->greeting = $greeting;
  $this->color    = join(array_map(fn($c) => sprintf('%02X',rand($c, 255)), [100,200,100]));

  return new Document('<main><h2 style="color:#${color}">hello ${greeting}</h2><!-- yield sample ! --></main>');

}, ['publish' => 3, 'title' => 'Dynamic Route']);



/**** IMPLEMENTATION **********************************************************/

try {

  $request = new Request($_SERVER);

  header("Content-Type: {$request->mime}");

  // php dev server handles static files too; prod could use this for cache
  if (is_file($request->uri)) {
    $output = Response::load($request);
    header('Content-Length: '. strlen($output));
  } else {

    // Set Application data
    $data = [
      'pages' => Route::gather(glob('pages/*.{html,xml,md}', GLOB_BRACE)),
      'date'  => fn ($format, $time = 'now') => date($format, strtotime($time)),
      'model' => 'model::FACTORY',
    ];

    $response = new Response($request, $data + CONFIG['data']);
    $output   = Route::delegate($response);

  }

} catch (Redirect $notice) {

  call_user_func($notice);

  exit;

} catch (Exception | Error $e) {

  http_response_code($e->getCode() ?: 400);

  $keys   = ['message', 'code', 'file', 'line', 'trace'];
  $data   = ['error' => array_combine($keys, array_map(fn($m) => $e->{"get$m"}(), $keys))];
  $output = Request::GET('error', $data + CONFIG['data']);

} finally {


  echo $output;

  exit;
}
