<?php require_once '../src/kernel.php';



/**** ROUTING ****************************************************************/


Controller\System::load('enabled');


Route::set('redirector', function () {
  throw $this->status(Status::REDIRECT, '/index');
  return new Document('<p>'.Status::REDIRECT.'</p>');
});


Route::promisy(function($message = 'go to /promisy/blorf') {

  $this->message = strtolower($message);
  $this->list    = [['key' => 'alpha'], ['key' => 'beta']];

  // visit  /promisy/blorf
  if ($this->message === 'blorf') {
    // setting 200 bypasses next action, because request is successful at this point
    $this->status(200);
  }

  return new Document('<main><h2>${message}</h2><!-- iterate list --><mark>${${message}}: ${key}</mark></main>');

})->then(function($payload){
  // otherwise document is not fullfilled, so the return value(s) of the
  // previous method become arguments to this method. This is useful for
  // caching (ie., Route::expensive(check)->then(do)->then(cache)
  // or authentication... ie, Route::path(authenticate)->then(do);
  $payload->documentElement->appendChild(new Element('h1', 'cool?'));

  // to see the final catch, uncomment
  // throw $this->status(404);

  return $payload;

})->catch(fn (Status $notice) => new Document('<p>this cannot happen</p>'));



Route::example(function($greeting = 'world') {

  $this->yield('sample', 'partials/table.md');

  $this->greeting = $greeting;
  $this->color    = join(array_map(fn($c) => sprintf('%02X',rand($c, 255)), [100,200,100]));

  return new Document('<main><h2 style="color:#${color}">hello ${greeting}</h2><!-- yield sample ! --></main>');

}, ['publish' => 3, 'title' => 'Dynamic Route']);




/**** IMPLEMENTATION **********************************************************/

try {

  $request  = new Request(headers: $_SERVER, root: realpath('.'));

  $response = Route::delegate(new Response($request, [
    'date'  => fn ($format, $time = 'now') => date($format, strtotime($time)),
    'model' => 'model::FACTORY',
  ]));

  // WIP These are some interesting examples data callbacks that allow templates to do more than they prob should.
  // 'instance' => fn($name, $id, ...$params) => "Model\\$name"::ID($id, ...$params),
  // 'factory'  => fn($name, $query) => Model::$name(urldecode($query)),

} catch (Status $notice) {

  $status = $notice->getCode();

  if ($status == $notice::NOT_FOUND) {
    $response = Request::GET('system/error/404', yield: false);
  } else {
    $response = new Response($notice->request);
    if ($status == $notice::CREATED) {
      // Represents an existing file—either cached or a static file
      $notice->request->setBody(file_get_contents($notice->getFile()));
    } elseif (! floor($status / 399)) {

      // represents a 3xx code, redirect to another resource
      $response->header('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0');
      $response->header("Location", $notice->getMessage());
    }
  }
} catch (Exception | Error $e) {

  $keys     = ['message', 'code', 'file', 'line', 'trace'];
  $data     = ['error' => array_combine($keys, array_map(fn($m) => $e->{"get$m"}(), $keys))];
  $response = new Response($request, $data);

  // at this level, router may have not finished preparing, so directly call a controller
  $response->compose((new Controller\System)->call($response, $response->route = 'error', $e->getCode() ?: 500));

} finally {

  echo $response;

}
