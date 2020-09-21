<?php

define('CONFIG', parse_ini_file('../data/config.ini'));

require_once '../src/kernel.php';



/*** IMPLEMENTATION *******************************************************************************/


try {
  
  if (CONFIG['dev'] ?? false) include 'edit.php';
    

  $request   = new Request($_SERVER, 'index.html');
  
  header("Content-Type: {$request->mime}");
  
  if (file_exists($request->uri)) {
    // php dev server handles static files too; prod this can config to cache as this would be done by http server
    $output = Response::load($request)->body;
    header('Content-Length: '. strlen($output));

  } else {

    // Set Application data
    $data = [
      'pages'       => Response::pair(glob('pages/*.*')), 
      'description' => 'A tiny templating framewqrk, no dependencies.',
      'timestamp'   => new DateTime,
      'title'       => 'Diatom Micro Framework',
    ];
    
    $response = new Response($request, $data);    
    $output   = Route::compose($response);

    if ($output instanceof Template) {
      $output = $output->render($response->data);
      if ($request->type == 'json'){
        $output = json_encode(simplexml_import_dom($output));
      }
    }
  }
  
} catch (Exception | Error $e) {

  http_response_code($e->getCode() ?: 400);
  // $toarr = (array)$e;
  $output = Request::open('error', [
    'wrapper' => CONFIG['DEV'] ?? null,
    'message' => $e->getMessage(),
    'code'    => $e->getCode(),
    'file'    => $e->getFile(),
    'line'    => $e->getLine(),
    'trace'   => array_reverse($e->getTrace()),
  ]);
  
} finally {

  echo $output;

}