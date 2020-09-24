<?php

define('CONF', parse_ini_file('../data/config.ini'));

require_once '../src/kernel.php';


/*** ROUTING **************************************************************************************/

// visit http://localhost:8888/example to see this play out
Route::example(function(int $padding = 2) {
  $this->message = "hello world";
  $this->padding = $padding;
  // note, this string is not interpolated, those are variables rendered into the template
  return new Document('<h1 style="padding: ${padding}em;">${message}</h1>');

});

/*** IMPLEMENTATION *******************************************************************************/


try {
  if (CONF['dev'] ?? false) include 'edit.php';
  
  $request   = new Request($_SERVER, 'index.html');
  
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