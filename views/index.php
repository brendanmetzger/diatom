<?php require_once '../src/kernel.php';



/**** ROUTING ****************************************************************/


Controller\System::load('enabled');


Route::promisy(function($message = 'add a param to address') {

  $this->message = strtolower($message);
  $this->list    = [['key' => 'alpha'], ['key' => 'beta']];
  // visit  /promisy/blorf to see how fullfilled might work
  $this->fulfilled = ($this->message === 'blorf');

  return new Document('<main><h2>${message}</h2><!-- iterate list --><mark>${${message}}: ${key}</mark></main>');

})->then(function($payload){
  // otherwise document is not fullfilled, so the return value(s) of the
  // previous method become arguments to this method. This is useful for
  // caching (ie., Route::expensive(check)->then(do)->then(cache)
  // or authentication... ie, Route::path(authenticate)->then(do);
  $payload->documentElement->appendChild(new Element('h1', 'cool?'));

  // to see the final catch, uncomment
  // throw $this->state(404);

  return $payload;

})->catch(fn (WIP_Status $notice) => new Document('<p>this cannot happen</p>'));



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


} catch (WIP_Status $notice) {

  $response = new Response($notice->request);
  $status   = $notice->getCode();
  if ($status == $notice::CREATED) {
    // Represents an existing file—either cached or a static file
    $notice->request->setBody(file_get_contents($notice->getFile()));
  } elseif (! floor($status / 399)) {
    // represents a 3xx code, redirect to another resource
    $response->header('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0');
    $response->header("Location", $notice);
  }



} catch (Redirect $notice) {

  // TODO: refactor this into above, and no need to exit, rather run regular headers on a standard response.
  call_user_func($notice);

  exit;

} catch (Exception | Error $e) {

  $keys     = ['message', 'code', 'file', 'line', 'trace'];
  $data     = ['error' => array_combine($keys, array_map(fn($m) => $e->{"get$m"}(), $keys))];

  $response = Request::GET('system/error', $data, yield: false);
  $response->status = $e->getCode() ?: 400;

} finally {


  echo $response;

  // TODO: flush everything, run cleanups

  exit;
}
