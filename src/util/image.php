<?php namespace util;

class Image {
  
  private $file, $resource, $width, $height, $aspect;
  
  public function __construct(\File $file)
  {
    $this->file      = $file;
    $this->resource  = imagecreatefromstring($this->file->body);
    $this->width     = imagesx($this->resource);
    $this->height    = imagesy($this->resource);
    $this->aspect    = $this->width / $this->height;
  }
  
  static public function load(string $path) {
    return new self(\File::load($path));
  }
  
  public function scale(int $dimension)
  {
    $this->resource = imagescale($this->resource, ($dimension * $this->aspect));
    return $this;
  }
  
  public function export(string $path, int $quality = 80)
  {
    $info = pathinfo($path);
    $this->file->uri = $path;
    $processor = str_replace('/', '', \File::MIME[$info['extension']]);

    ob_start();
    call_user_func($processor, $this->resource, null, 80);
    $this->file->body = ob_get_clean();
    return $this->file;
  }
  
  public function __destruct() {
    imagedestroy($this->resource);
  }
}