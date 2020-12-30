<?php

class HTTP {
  const UA = 'Diatom Request';

  static public function GET($url, array $headers = []): self {
    return new self('GET', $url, $headers);
  }

  static public function POST($url, array $data, array $headers = [], ?callable $callback = null): Response {
    $headers['content-type'] ??= 'multipart/form-data';
    return self::make('POST', $url, $data, $headers, $callback);
  }

  static public function PATCH($url, array $data, array $headers = []): Response {
    return self::make('PATCH', $url, $data, $headers, null);
  }

  static private function make(string $method, string $url, ?array $data, array $headers, ?callable $callback)
  {
    $response = new Response(new Request(array_merge($headers, [
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
    if (preg_match('/application\/\S*json/', $type))
      $response->merge(json_decode($response->body, true));
    else if (str_ends_with($type, 'ml'))
    else if (substr($type, -2) == 'ml')
      $response->data = Document::open($response);

    return $response;
  }

  private $response, $handle;
  private function __construct(private string $method, private string $url, private array $headers)
  {
    $this->response = new Response(new Request(array_merge($headers, [
      'REQUEST_URI'    => $url,
      'REQUEST_METHOD' => $method,
      'CONTENT_TYPE'   => $headers['content-type'] ?? null,
    ])));

    curl_setopt_array($this->handle = curl_init(), [
      CURLOPT_CUSTOMREQUEST    => $method,
      CURLOPT_USERAGENT        => self::UA,
      CURLOPT_URL              => $url,
      CURLOPT_HEADER           => false,
      CURLOPT_HEADERFUNCTION   => [$this->response, 'header'],
      CURLOPT_RETURNTRANSFER   => true,
    ]);

    foreach ($headers as $key => &$header) {
      $name = mb_convert_case(strtolower(str_replace('_', '-', $key)), MB_CASE_TITLE, "UTF-8");
      $header =  "{$name}: {$header}";
    }

    if (! empty($headers))
      curl_setopt($this->handle, CURLOPT_HTTPHEADER, array_values($headers));
  }

  public function send($data = null, ?callable $callback = null)
  {
    if ($data && $this->method != 'GET' ) {
      if (str_contains($this->headers['content-type'], 'json'))
        $data = json_encode($data);

      curl_setopt($this->handle, CURLOPT_POSTFIELDS, $data);
    }

    if ($callback) {
      curl_setopt($this->handle, CURLOPT_NOPROGRESS, false);
      curl_setopt($this->handle, CURLOPT_PROGRESSFUNCTION, $callback);
    }

    // TODO: see if setBody could take care of all of this.
    $this->response->setBody(curl_exec($this->handle));

    // $type = explode(';', $this->response->headers['content-type'] ??= 'text/plain')[0];
    //
    // if (preg_match('/application\/\S*json/', $type))
    //   $this->response->merge(json_decode($this->response->request->body, true));
    // else if (substr($type, -2) == 'ml')
    //   $this->response->data = Document::open($this->response);

    return $this->response;
  }
}
