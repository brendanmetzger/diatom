<?php namespace util;
###################################################################################################
# Bench 
## Used to send progress messages in CLI, use `split` to mark a time findable by key, and if two
## args are passed in, it will return the time elapsed since previous key. Use Log to capture log
## messages for output or recording.

class Bench {
  
  public $counter = 1;
  
  private $mark  = [],
          $split = [];
  
  static public $log = [];
  
  public function __construct($key = 'start', $counter = 1) {
    $this->split($key);
    $this->counter = $counter;
  }
  
  public function split($key, $split = false) {
    $this->mark[$key] = hrtime(true);
    if ($split) $this->split[$key] = ($this->mark[$key] - $this->mark[$split]) / 1e+6;
    return $split ? $this->split[$key] : $this->mark[$key];
  }
  
  // use task count to draw a progress bar, useful for slower processes with lots of stuff to do
  public function progress($index, $msg = '', $total = 50):string  {
    $prog = $index / $this->counter;
    $crlf = ($prog === 1) ? "\n" : "\r";
    return sprintf("% 5.1f%% [%-{$total}s] %s %s", $prog * 100, str_repeat('#', round($total * $prog)), $msg, $crlf);
  }
  
  static public function log($msg) {
    self::$log[] = print_r($msg, true);
  }
}