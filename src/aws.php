<?php


class AWS {
  
  private $algo = 'AWS4-HMAC-SHA256',
          $scope, $cred, $secret, $time, $host;
  
  private function __construct($service, string $version, $id, $secret, $region) {
    
    if ($service == 's3') $this->host = "https://{$version}.s3.{$region}.amazonaws.com/";
    else                  $this->host = "https://{$service}.{$region}.amazonaws.com/{$version}";
   
    $this->time   = time();
    $this->scope  = [gmdate('Ymd', $this->time), $region, $service, 'aws4_request'];
    $this->cred   = $id .'/'. implode('/', $this->scope);
    $this->secret = array_reduce($this->scope, fn($k, $m) => hash_hmac('sha256', $m, $k, true), 'AWS4'.$secret);
  }
    
  static public function Transcribe(array $configuration) {
    // There is no http api reference, so this will need to be pieced together trial and error
    return new self('transcribe', '2017-10-26', ...$configuration);
  }

  static public function Transcode(array $configuration) {
    return new self('elastictranscoder', '2012-09-25', ...$configuration);
  }
  
  static public function S3(array $configuration, $bucket = '') {
    return new self('s3', $bucket, ...$configuration);
  }
  
  private function authorize($url, array $headers, $body, $method = "POST")
  {
    $parsed = parse_url($url);
    $headers['host'] = $parsed['host'];

    ksort($headers);

    $keys    = array_map('strtolower', array_keys($headers));
    $chead   = array_map(fn($k, $v) => $k.':'.preg_replace('/\s+/', ' ', $v), $keys, $headers);
    $request = [
      'method'  => $method,
      'uri'     => implode('/', array_map('rawurlencode', explode('/', $parsed['path'] ?? '/'))),
      'query'   => rawurlencode($parsed['query'] ?? ''),
      'headers' => implode("\n", $chead) . "\n",
      'signed'  => implode(";", $keys),
      'payload' => hash('sha256', $body),
    ];
        
    $msg = implode("\n", [
      $this->algo,
      $headers['x-amz-date'], 
      implode('/', $this->scope),
      hash('sha256', implode("\n", $request)),
    ]);
    
    $auth = "{$this->algo} Credential={$this->cred}, SignedHeaders=%s, Signature=%s";
    
    return sprintf($auth, $request['signed'], hash_hmac('sha256', $msg, $this->secret));
  }
  
  public function job($from, $to, $pipeline, $preset)
  {
    $endpoint  = $this->host.'/jobs';

    $job = [
      'PipelineId' => $pipeline,
      'Input'      => ['Key' => $from],
      'Output'     => ['Key' => $to, 'PresetId' => $preset]
    ];
        
    $body = json_encode($job);
    
    $headers = [
      'x-amz-date'     => gmdate("Ymd\THis\Z", $this->time),
      'content-type'   => 'application/json',
      'content-length' => strlen($body),
    ];
    
    $headers['Authorization'] = $this->authorize($endpoint, $headers, $body);
    return HTTP::POST($endpoint, $job, $headers);
  }
  
  
  public function upload(File $file, string $bucket, ?callable $callback = null, $mb = 150)
  {  
    $endpoint = "https://{$bucket}.s3.amazonaws.com/";
    
    $rules = [
      ['content-length-range', '1', $mb*10**6],
      ['bucket' => $bucket]
    ];

    if ($file->size > $rules[0][2]) throw new Exception("File too large to upload as configured");

    return HTTP::POST($endpoint, $this->policy($file, $rules), [], $callback);
  }
  
  public function xhr($key, $bucket)
  {
    $rules = [
      ['content-length-range', '1', '500000'],
      ['bucket' => $bucket]
    ];
    return json_encode([
      'action' => "https://{$bucket}.s3.amazonaws.com/",
      'input' => $this->policy(new File($key), $rules),
    ]);
  }
  
  private function policy(File $file, array $rules, array $meta = []): array
  {
    # Remap meta keys to prefix with x-amz-meta- (these are optional)
    $meta = array_combine(array_map(fn($key) => "x-amz-meta-{$key}", array_keys($meta)), $meta);
    
    $fields  = array_merge([
      'acl'                     => 'public-read',
      'key'                     => trim($file->uri, '/'),
      'content-type'            => $file->mime,
      'x-amz-credential'        => $this->cred,
      'x-amz-algorithm'         => $this->algo,
      'x-amz-date'              => gmdate("Ymd\THis\Z", $this->time),
      'x-amz-storage-class'     => 'STANDARD_IA',
    ], $meta);
    
    # Conditions are required by the policy, and need $key => $val of $fields within an array
    $conditions = array_map(fn($key, $value) => [$key => $value], array_keys($fields), $fields);
    
    $fields['policy'] = base64_encode(utf8_encode(json_encode([
      'expiration'  => gmdate('Y-m-d\TG:i:s\Z', $this->time + 1800),
      'conditions'  => array_merge($conditions, $rules),
    ])));
      
    $fields['x-amz-signature'] = hash_hmac('sha256', $fields['policy'], $this->secret);
    
    if (! empty($file->body)) {
      $fields['file'] = $file->body;
    }
    
    
    return $fields;
  }
}
