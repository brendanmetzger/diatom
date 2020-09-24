<?php namespace util;
/**
 * Serialize ints to alpha and vice versa
 * Rationale: xml ids cannot start with numbers, but there are 500+ valid characters; makes for
 *            shorter (visually) serialized IDs. Works with same default sort as numerical digits
 * reference: https://www.w3.org/TR/REC-xml/#NT-Name
 * ":" | [A-Z] | "_" | [a-z] | [#xC0-#xD6] | [#xD8-#xF6] | [#xF8-#x2FF] | [#x370-#x37D] | [#x37F-#x1FFF] 
 *| [#x200C-#x200D] | [#x2070-#x218F] | [#x2C00-#x2FEF] | [#x3001-#xD7FF] | [#xF900-#xFDCF] | 
 * [#xFDF0-#xFFFD] | [#x10000-#xEFFFF]
 */

class Serial {
  const UTF = [
    'A-Z' => ["\u{041}", "\u{05a}"],
    'a-z' => ["\u{061}",  "\u{07a}"],
    [0xC0,  0xD6], // À-Ö
    [0xD8,  0xF6], // Ø-ö
    [0xF8,  0x2FF],// ø-˿
    [0x1401,0x1676],  // Unified Canadian Aboriginal Syllabics
    'runes'   => [0x16A0, 0x16F0], 
    'braille' => ["\u{02800}","\u{028FF}"], // (interesting for encoding, but out of range for @id)

  ];
  
  const CODEX = ['A','B','C','D','E','F','G','H','I','J','K','L','M',
                 'N','O','P','Q','R','S','T','U','V','W','X','Y','Z',
                 'a','b','c','d','e','f','g','h','i','j','k','l','m',
                 'n','o','p','q','r','s','t','u','v','w','x','y','z'];
  
  protected $codex, $base;
  private function __construct($codex = null) {
    $this->codex = $codex ?? self::CODEX;
    $this->base  = count($this->codex);
  }
  
  static public function id ($input) {
    $method = 'from'.gettype($input);
    $instance = new self;
    return $instance->{$method}($input);
  }
  
  // this function turns any string into a predictable, 5 character string, useful for making ID's
  static public function hash(string $input)
  {
    static $instance = null;
    
    $instance ??= new self(array_merge(self::CODEX, range(0,9)));
    $input      = md5(mb_strtolower(preg_replace('/[. ]/', '', $input)));
    $decimal    = intval(substr($input, 0, 9), 16);

    return substr($instance->fromInteger($decimal), 0, 5);
  }
  
  protected function fromInteger(int $in, string $out = '') { 
    do  {
      $d   = floor($in / $this->base);
      $r   = $in % $this->base;
      $in  = $d;
      $out = $this->codex[$r] . $out;
    } while ($in > 0);

    return $out;
  }
  
  protected function fromString(string $in, int $out = 0) {
    $codex = array_flip($this->codex);
    foreach (array_reverse(preg_split('//u', $in, null, PREG_SPLIT_NO_EMPTY)) as $exp => $val)
      $out += ($this->base ** $exp) * $codex[$val];
    return $out;
  }
  
  static public function mb_range($start, $end, array $output = []) {

    if ($start == $end) return [$start];  // no range given

    // get unicodes of start and end
    list(, $_current, $_end) = unpack("N*", mb_convert_encoding($start . $end, "UTF-32BE", "UTF-8"));

    $cursor = $_end <=> $_current; // determine ascending or decending
  
    do {
      $output[]  = mb_convert_encoding(pack("N*", $_current), "UTF-8", "UTF-32BE");
      $_current += $cursor;
    } while ($_current != $_end);
    $output[] = $end;
    return $output;
  }
  
}
