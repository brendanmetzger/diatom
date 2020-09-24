<?php namespace util;


class Sprite extends \File
{
  public $bg = [250,250,250], $index, $cell, $capacity, $root;
  
  static private $sprites = [];
  
  # A singleton-esque static constructor insures even if thousands of individual 'cells'
  ##  are getting updated, the main sprite only needs to be instantiated once
  static private function instance($path)
  {
    return self::$sprites[$path] ??= new self($path);
  }
  
  private function __construct($path)
  {
    parent::__construct($path);
    [$this->index, $this->cell, $this->capacity] = explode('.', $this->info['filename']);

    $this->root  = ceil(sqrt($this->capacity));
    $xy = $this->cell * $this->root;

    $this->target = (object)['width' => $this->cell, 'height' => $this->cell];

    if (file_exists($path)) {
      $this->composite = imagecreatefromjpeg($path);
    } else {
      
      $this->composite = imagecreatetruecolor($xy, $xy);
      // set a white-ish background color
      $this->fill([0,0,$xy, $xy], $this->bg);
    }
  }
    
  public function __destruct()
  {
    imagejpeg($this->composite, $this->uri, 100);
    imagedestroy($this->composite);
    echo "...saved <{$this->uri}>\n";
    
  }
  
  public function export(string $type, int $resolution)
  {
    $method = "image{$type}";
    ob_start(); // start output buffering; convert/format image; flush buffer, assign response body
    $method($this->composite, null, $resolution);
    return $this->body = ob_get_clean();
  }
  
  
  public function fill(array $bbox, array $rgb)
  {
    array_push($bbox, imagecolorallocate($this->composite, ...$rgb));
    imagefilledrectangle($this->composite, ...$bbox);
  }
  
  public function set($index, Image $image)
  {
    // as each sprite has a capacity, the index is scaled to map to the correct position on a sprite
    $index       = $index - ($this->capacity * $this->index);
    $destination = clone $this->target;
  
    if ($image->aspect > 1)
      $destination->height /= $image->aspect;
    else
      $destination->width *= $image->aspect;
  
    
    // The destination image is a checkboard, with square cells. The copy image may be any aspect, so
    // compute offset of row/column, then add the offset according to the aspect of the copy image
    $cell = [
      'x' =>      ($index % $this->root) * $this->target->width,
      'y' => floor($index / $this->root) * $this->target->height,
    ];
    
    $destination->x = $cell['x'] + round(($this->target->width  - $destination->width)  / 2);
    $destination->y = $cell['y'] + round(($this->target->height - $destination->height) / 2); 
    
    $copy = [
      'destination'        => $this->composite,
           'source'        => $image->resource,
      'destination_x'      => $destination->x,
      'destination_y'      => $destination->y,
           'source_x'      => 0,
           'source_y'      => 0,
      'destination_width'  => $destination->width,
      'destination_height' => $destination->height,
           'source_width'  => $image->width,
           'source_height' => $image->height,
    ];
    
    
    $cell['offset_width']  = $cell['x'] + $this->cell;
    $cell['offset_height'] = $cell['y'] + $this->cell;

    // Paint over cell spot with bg color, incase the image has changed
    $this->fill(array_values($cell), $this->bg);
    
    imagecopyresampled(...array_values($copy));
  }
  
  
  static public function update(int $cellsize, array $boardsize, \Data $images)
  {
    $log  = new Bench('start', $images->length);
    $area = array_product($boardsize);
    
    // TODO deal with path config here

    foreach ($images as $counter => $image) {
      $path = sprintf('media/sprites/origin/%s.%s.%s.jpg', ceil($image['idx'] / $area) - 1, $cellsize, $area);
      self::instance($path)->set($image['idx'], new Image($image['blob']));
      echo $log->progress($counter, "processing {$counter}");
    }
    
    return self::$sprites;
  }
}