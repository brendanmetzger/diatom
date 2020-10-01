<?php

define('CONF', parse_ini_file('../data/config.ini'));

require_once '../src/kernel.php';


/*** ROUTING *****************************************************************/



Route::example(function($message = 'world') {
  $this->message = "hello {$message}";
  $this->color   = join(array_map(fn($idx) => dechex(rand(0, $idx)), array_fill(0, 3, 255)));


  return new Document('<h1 style="color: #${color};">${message}</h1>');

}, ['publish' => 3, 'title' => 'Dynamic Route']);



/*** IMPLEMENTATION **********************************************************/


try {
  if (CONF['dev'] ?? false) include 'edit.php';
  
  $request   = new Request($_SERVER);
  
  header("Content-Type: {$request->mime}");
  
  if (is_file($request->uri)) {
    // php dev server handles static files too; prod could use this for cache
    $output = Response::load($request)->body;
    header('Content-Length: '. strlen($output));

  } else {
    
    
    // Set Application data
    $data = [
      'pages' => Route::gather(glob('pages/*.*')), 
      'about' => 'A modeled templating framework, no dependencies.',
      'date'  => fn ($format, $time = 'now') => date($format, strtotime($time)),
      'title' => 'Diatom Micro Framework',
      'wrapper' =>  CONF['DEV'] ?? null,
      'model'   => 'model::FACTORY',
      'list' => [['name' => "A", 'other' => 'goo'], ['name' => 'B', 'other' => 'yep'], ['name' => 'D', 'other' => 'tope tope']],
    ];
    
    $response = new Response($request, $data);    
    $output   = Route::compose($response);

    if ($output instanceof Template) {
      $output = Render::DOM($output->render($response->data));
      
      if ($request->type == 'json')
        $output = json_encode(simplexml_import_dom($output));
    }
  }
  
} catch (Exception | Error $e) {

  http_response_code($e->getCode() ?: 400);
  // $toarr = (array)$e;
  $output = Request::GET('error', [
    'wrapper' => CONF['DEV'] ?? null,    
    'message' => $e->getMessage(),
    'code'    => $e->getCode(),
    'file'    => $e->getFile(),
    'line'    => $e->getLine(),
    'trace'   => $e->getTrace(),
  ]);
  
} finally {

  echo $output;
  

}