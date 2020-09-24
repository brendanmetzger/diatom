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
  
  public function scale(int $dimension, int $quality = 80)
  {
    $scaled   = imagescale($this->resource, ($dimension * $this->aspect));
    ob_start();
    imagejpeg($scaled, null, $quality);
    return ob_get_clean();
  }
  
  public function save(string $type = null)
  {
    # code...
  }
  
  public function __destruct() {
    imagedestroy($this->resource);
  }
}