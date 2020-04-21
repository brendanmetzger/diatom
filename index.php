<?php

require 'diatom.php';

/*** IMPLEMENTATION *******************************************************************************/

try {
  define('DOC', ['layout' => 'pages/index.html', 'error' => 'pages/error.html']);
  
  // TODO, make $store private Data::store('title', 'MCA Chicago Web Technology Audit');
  Data::$store['title']       = 'Diatom Micro Framework';
  Data::$store['description'] = 'A tiny templating framewqrk, no dependencies.';
  Data::$store['time']        = new DateTime;
  Data::$store['pages']       = Route::gather('pages/*.*', 'chapter');
  Data::$store['request']     = parse_url($_SERVER['REQUEST_URI']);


  $template = Route::delegate('content', new Template(DOC['layout']));
  $output   = Renderer::organize(Renderer::sectionize($template->render(Data::$store)));
  
} catch (Exception $e) {
  
  $output = '<h1>'.$e->getMessage().'</h1><pre>'.print_r($e->getTrace(), true).'</pre>';
  
} finally {
  
  header('Content-Type: application/xhtml+xml; charset=utf-8');
  echo "<!DOCTYPE html>\n"; // anything but this triggers quirks mode
  $output->documentElement->setAttribute('xmlns', 'http://www.w3.org/1999/xhtml');
  echo $output;
  // echo "<!--". memory_get_peak_usage() / 1000 . "Kb memory -->\n";
  
}
