<?php

// Start development server with `php -S 127.0.0.1:pppp index.php`

require_once 'src/diatom.php';



/*** IMPLEMENTATION *******************************************************************************/

try {
  
  $request   = new Request($_SERVER, 'index.html');
  
  header("Content-Type: {$request->mime}");
  
  if (file_exists($request->uri)) {
    
    $output = Response::load($request)->body;
    header('Content-Length: '. strlen($output));
    
  } else {
    
    // Set Application data
    $data = [
      'pages'     => Response::gather(glob('pages/*.*')), 
      'description' => 'A tiny templating framewqrk, no dependencies.',
      'timestamp' => new DateTime,
      'title'     => 'Diatom Micro Framework',
    ];
    
    $response = new Response($request);
    $output   = Route::compose($response);

    if ($output instanceof Template) {
      $output = $output->render($response->merge($data));
    }
        
    if ($request->type == 'json'){
      $output = json_encode(simplexml_import_dom($output));
    }
    
  }
  
} catch (Exception | Error $e) {

  http_response_code($e->getCode() ?: 400);

  $output = Request::GET('error')->render([
    'wrapper' => 'txmt://open',
    'message' => $e->getMessage(),
    'code'    => $e->getCode(),
    'file'    => $e->getFile(),
    'line'    => $e->getLine(),
    'trace'   => array_reverse($e->getTrace()),
  ]);
  
} finally {

  echo $output;
  
}