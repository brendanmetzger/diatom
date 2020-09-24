<?php

define('CONFIG', parse_ini_file('../data/config.ini'));

require_once '../src/kernel.php';


/*** ROUTING **************************************************************************************/

/*** IMPLEMENTATION *******************************************************************************/


try {
  if (CONFIG['dev'] ?? false) include 'edit.php';
  
  $request   = new Request($_SERVER, 'index.html');
  
  header("Content-Type: {$request->mime}");
  
  if (is_file($request->uri)) {
    // php dev server handles static files too; prod could use this for cache
    $output = Response::load($request)->body;
    header('Content-Length: '. strlen($output));

  } else {

    // Set Application data
    $pages = Route::gather(glob('pages/*.*'));
    
    $data = [
      'pages'       => $pages, 
      'description' => 'A modeled templating framework, no dependencies.',
      'timestamp'   => new DateTime,
      'title'       => 'Diatom Micro Framework',
      'test'        => [['key' => 'A'], ['key' => 'B']],
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
    'wrapper' => CONFIG['DEV'] ?? null,
    'message' => $e->getMessage(),
    'code'    => $e->getCode(),
    'file'    => $e->getFile(),
    'line'    => $e->getLine(),
    'trace'   => $e->getTrace(),
  ]);
  
} finally {

  echo $output;
  echo "<!--".(memory_get_peak_usage()/1000)."kb-->";
  

}