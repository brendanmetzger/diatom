<?php namespace model;

/**
 * this class represents a thing capable of an authorized action, autonomoustly or otherwise.
 */
class Agency extends \Model implements \Authorized
{
  
  
  static public function check(\ReflectionMethod $method)
  {
    $model = $method->getParameters()[0]->getType()->getName();
    $method->setAccessible(true);
    return new $model((new \Document('<p>TODO: auth routine</p>')));
  }
  
  public function validate($token) {
    [$hash, $token] = str_split($token, strlen($token) / 2);
    if (!hash_equals($hash, hash_hmac(strtok(CONFIG['hmac'], ':'), $token, strtok(':')))) {
      Redirect::local(CONFIG['auth']['url']."authorize?scope=user:email,read:org&client_id=".CONFIG['auth']['id']);
    }
    return true;
  }
  
}