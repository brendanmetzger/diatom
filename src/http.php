<?php

class HTTP {
  function __construct() {
    echo "loaded ok";
  }
  
  static private function make(string $method, string $url, ?array $data, array $headers, ?callable $callback)
  {
    $response = new Response(new self(array_merge($headers, [
      'REQUEST_URI'    => $url,
      'REQUEST_METHOD' => $method,
      'CONTENT_TYPE'   => $headers['content-type'] ?? null,
    ])));
    
    curl_setopt_array($ch = curl_init(), [
      CURLOPT_CUSTOMREQUEST    => $method,
      CURLOPT_USERAGENT        => self::UA,
      CURLOPT_URL              => $url,
      CURLOPT_HEADER           => false,
      CURLOPT_HEADERFUNCTION   => [$response, 'header'],
      CURLOPT_RETURNTRANSFER   => true,
    ]);
    
    if ($method != 'GET') {
      if ($headers['content-type'] == 'application/json')
        $data = json_encode($data);
      
      if (! empty($data))
        curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
    }
    
    foreach ($headers as $key => &$header) {
      $name = mb_convert_case(strtolower(str_replace('_', '-', $key)), MB_CASE_TITLE, "UTF-8");
      $header =  "{$name}: {$header}";
    }
    
    if (! empty($headers))
      curl_setopt($ch, CURLOPT_HTTPHEADER, array_values($headers));
    
    if ($callback) {
      curl_setopt($ch, CURLOPT_NOPROGRESS, false);
      curl_setopt($ch, CURLOPT_PROGRESSFUNCTION, $callback);
    }
    
    $response->body = curl_exec($ch);
    
    curl_close($ch);
    
    $type = explode(';', $response->headers['content-type'] ??= 'text/plain')[0];
    if (strpos($type, 'application/json') !== false)
      $response->merge(json_decode($response->body, true));
    else if (substr($type, -2) == 'ml')
      $response->data = Document::open($response);
    
    return $response;
  }
  
}
?>