<?php require_once '../src/kernel.php';



/**** ROUTING ****************************************************************/


Controller\System::load('enabled');


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

  // WIP These are some interesting methods that allow templates to do more than they prob should.
  // 'instance' => fn($name, $id, ...$params) => "Model\\$name"::ID($id, ...$params),
  // 'factory'  => fn($name, $query) => Model::$name(urldecode($query)),

  $response = Route::delegate(new Response($request, [
    'date'  => fn ($format, $time = 'now') => date($format, strtotime($time)),
    'model' => 'model::FACTORY',
  ]));

} catch (WIP_Status $notice) {

  if ($notice->getCode() == 201) {
    $notice->request->body = file_get_contents($notice->location);
  }

  $response = new Response($notice->request);

} catch (Redirect $notice) {

  // TODO: refactor this into above, and no need to exit, rather run regular headers on a standard response.
  call_user_func($notice);

  exit;

} catch (Exception | Error $e) {


  $keys   = ['message', 'code', 'file', 'line', 'trace'];
  $data   = ['error' => array_combine($keys, array_map(fn($m) => $e->{"get$m"}(), $keys))];
  $response = Request::GET('system/error', $data, yield: false);
  $response->status = $e->getCode() ?: 400;

} finally {


  echo $response;

  // TODO: flush everything, run cleanups

  exit;
}
