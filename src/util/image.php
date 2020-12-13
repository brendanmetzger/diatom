<?php namespace util;

/**
 * Some (light) Image Manipulation Possibilities
 *
 * Uses gd (sudo apt-get install php-gd), and if using digest, gmp (sudo apt-get install php-gmp)
 */

class Image {
  
  
  public $file, $resource, $width, $height, $aspect;
  
  public function __construct(\File $file)
  {
    $this->file     = $file;
    $this->resource = imagecreatefromstring($this->file->body);
    $this->width    = imagesx($this->resource);
    $this->height   = imagesy($this->resource);
    $this->aspect   = $this->width / $this->height;
  }
  
  static public function load(string $path) {
    return new self(\File::load($path));
  }
  
  /**
   * A simple way to look for duplicate images
   * make image grayscale, resize a n x n grid, generate a map of each pixel value, returns a
   * 64 bit hex string representing a 'fingerprint' of the image.
   * @type = difference|average — algo for fingerprint. difference is more accurate for duplicates
   */
  public function digest($type = "difference", $root = 8)
  {
    imagefilter($this->resource, IMG_FILTER_GRAYSCALE);
    $this->scale($root, $root);
    
    // $rgb = [($rgb >> 16) & 0xFF, ($rgb >> 8) & 0xFF, ($rgb >> 0) & 0xFF];
    // since this is grayscale, r or g or b will say the same thing, which is used below
    $pixels  = array_map(fn($i) => imagecolorat($this->resource, $i % $root, intdiv($i, $root)) & 0xFF, range(0,  $root**2 - 1));
    
    if ($type == 'average') {
      
      $average = floor(array_sum($pixels) / count($pixels));
      $bitmap  = array_map(fn($px) =>$px > $average ? 1 : 0, $pixels);
    
    }
    else if ($type == 'difference') {
      // instead of n+1 x n image, this keeps square w/ ccw cylindrical map (leftmost px vs. rightmost)
      $bitmap = array_reduce($pixels, function($c, $v) use($pixels, $root){
       $i   = count($c);
       $n   = ($i - 1 + $root) % $root + intdiv($i, $root) * $root;
       $c[] = $pixels[$n] > $v ? 1 : 0;
       return $c;
      }, []);
    }
    

    // convert to shorter strings
    $hex = gmp_strval(gmp_init(join($bitmap), 2), 16);
    $pad = count($bitmap) / 4;

    // return a padded hexadecimal string for formatting consistency
    return '0x'.str_pad($hex, $pad, 0, STR_PAD_LEFT);
  }
  

  
  public function scale(?int $width = null, ?int $height = null)
  {
    if ($width) {
      $height ??= $width / $this->aspect;
      $resize = imagecreatetruecolor($width, $height);

      imagesetinterpolation($resize, IMG_BICUBIC);
      imagecopyresampled($resize, $this->resource, 0, 0, 0, 0, $width, $height, $this->width, $this->height);
      imagedestroy($this->resource);
      $this->resource = $resize;
      $this->width = $width;
      $this->height = $height;
    }
    
    return $this;
  }
  
  public function export(string $path, int $quality = 80)
  {
    $info = pathinfo($path);
    $this->file->uri = $path;
    $processor = str_replace('/', '', \File::MIME[$info['extension']]);

    ob_start();
    call_user_func($processor, $this->resource, null, $quality);
    $this->file->body = ob_get_clean();
    return $this->file;
  }
  
  public function __destruct() {
    imagedestroy($this->resource);
  }
}