<?php define('CONFIG', parse_ini_file('../data/config.ini', true));
      require_once '../src/kernel.php';

      $mark = new util\bench;

/**** ROUTING ****************************************************************/



Route::usher(function($message) {
  // $this->fulfilled = true;
  return [new Document('<main><h2>${message}</h2></main>'), strtoupper($message)];
  
})->then(function($payload, $message){
  $this->message = $message;
  $payload->documentElement->appendChild(new Element('h1', 'cool?'));
  return $payload;
});



Route::example(function($message = 'world') {
  $this->yield('sample', 'pages/_samp.html');
  $this->message = "hello {$message}";
  $this->color   = join(array_map(fn($c) => sprintf('%02X',rand($c, 255)), [100,200,100]));

  // return new Document('<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 1000 600"/>');
  return new Document('<main><h2 style="color:#${color}">${message}</h2><!-- yield sample ! --></main>');
}, ['publish' => 3, 'title' => 'Dynamic Route']);



/**** IMPLEMENTATION **********************************************************/


try {
  
  if (CONFIG['dev'] ?? false) include 'edit.php';
  
  $request   = new Request($_SERVER);
  
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
      'list' => [['name' => "A", 'other' => 'goo'], ['name' => 'B', 'other' => 'yep'], ['name' => 'D', 'other' => 'tope tope']],
    ];
    
    $response = new Response($request, $data + CONFIG['data']);
    $output   = Route::delegate($response);
  }
  
} catch (Redirect $notice) {
  
  call_user_func($notice);

  exit(0);
  
} catch (Exception | Error $e) {

  http_response_code($e->getCode() ?: 400);

  $keys   = ['message', 'code', 'file', 'line', 'trace'];
  $data   = array_combine($keys, array_map(fn($m) => $e->{"get$m"}(), $keys));
  $output = Request::GET('error', $data + CONFIG['data']);
  
} finally {
  

  echo $output;
  
  // echo "<!-- " . (memory_get_peak_usage() / 1000). "kb -->\n";
  // echo "<!-- " . ($mark->split('end', 'start')). "ms -->\n";
  exit(0);
}