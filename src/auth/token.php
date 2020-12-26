<?php namespace Auth;

// TODO: use the configurable trait

/* The token represents a protected string that can be
 * exposed publicly. The general goal of this class is
 * to authenticate whether the public component of the
 * string was generated within the application
 *
 * Part II, it will also generate a token through the oAuth
 * handshake, and can save that to a cookie to authenticated
 * further requests.  The public string in that case represents
 *  an ID lookup that can generate some model
 */

class Token
{
  const NAME = 'token';

  private $message, $hmac;
  private static $auth;

  public function __construct(private string $token, private string $hmac = '') {}


  public function __toString() {
    return $this->message;
  }


  public function save($message)
  {
    $options = self::config('cookie');
    $options['expires'] += time();

    setcookie(self::NAME, $this->write($message), $options);
  }


  public function headers($type)
  {
    return [
      'Content-Type'  => \File::MIME[$type],
      'Authorization' => self::NAME . ' ' . $this->token,
    ];
  }


  public function write(string $message)
  {
    [$algo, $key]  = explode(':', self::config('hmac'));
    $this->message = $message;

    return join([
      $this->token,
      self::B64($message),
      hash_hmac($algo, $this->token . $message, $key),
    ]);
  }


  static protected function config($key, ...$args)
  {
    $out = (self::$auth ??= CONFIG['auth'])[$key];
    return is_string($out) ? vsprintf($out, $args) : $out;
  }


  /*
   * A base 64 string en/de/coder (and obfuscator, for good measure)
   *
   */
  static public function B64($input, bool $encode = true) {
      $input  = (! $encode) ? base64_decode($input) : $input;
      $length = (strlen($input) % 20) + 1;
      $alpha  = ['0123456789AaBbCcDdEeFfGgHhIiJjKkLlMmNnOoPpQqRrSsTtUuVvWwXxYyZz@ ._'];
      array_splice($alpha, (int) $encode, 0, substr($alpha[0], $length) . substr($alpha[0], 0, $length));
      $input = strtr($input, ...$alpha);
      return $encode ? base64_encode($input) : $input;
  }


  static public function verify($digest, $shift = 40): ?self
  {
    $token = new self(substr($digest, 0, $shift), substr($digest, 1+~$shift));
    $hash  = $token->write(self::B64(substr($digest, $shift, 1+~$shift), false));

    return hash_equals($hash, $digest) ? $token : null;
  }


  static public function generate($code) :self
  {
    if ($code) {
      $data = [
        'client_id'     => self::config('id'),
        'client_secret' => self::config('secret'),
        'code'          => $code,
      ];

      $post = \HTTP::POST(self::config('endpoint', 'access_token'), $data, ['Accept' => 'application/json']);

      if ($token = $post->data['access_token'] ?? false) {
        return new self($token);
      }
    }

    throw new \Status('Unauthorized', 401);
  }


  static public function authorize(\Request $request)
  {
    $instance = new self(sha1(mt_rand()));
    $endpoint = 'authorize?' . http_build_query([
      'scope'     => self::config('scope'),
      'client_id' => self::config('id'),
      'state'     => $instance->write($request->origin),
    ]);

    throw new \Redirect(self::config('endpoint', $endpoint));
  }

  static public function invalidate(): void {
    setcookie(self::NAME, 'l8r', time() - 3600);
  }
}
