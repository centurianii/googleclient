<?php
namespace g3;

/**
 * Utilities.
 */
class Utils {
   /**
    * Constructor.
    * 
    * preconditions: none.
    * postconditions: builds a Utils object.
    * 
    * @return Utils A Utils object
    */
   public function __construct() {}
   
      /**
    * It splits a GET request to a hashed array of keys-values and returns the
    * array or false if, it fails to make the split.
    * 
    * preconditions: none.
    * 
    * postconditions: see some examples for different values of $req argument
    * -'' returns false
    * -'key' returns array('key' => null),
    * -'key=' returns array('key' => ''),
    * -'key=   ' returns array('key' => ''),
    * -'key=value' returns array('key' => 'value'),
    * -'key=   value    ' returns array('key' => 'value'),
    * -'key=value&' returns array('key' => 'value'),
    * -'key=value&=' returns array('key' => 'value'),
    * -'key=value1&=value2' returns array('key' => 'value1'),
    * -'key=value1&   =value2' returns array('key' => 'value1'),
    * -'key1=value1&key2=value2' returns array('key1' => 'value1', 'key2' => 'value2'),
    * -'key1=value1& key2  =    value2  ' returns array('key1' => 'value1', 'key2' => 'value2'),
    * -'key1=value1& key2  =      ' returns array('key1' => 'value1', 'key2' => 'value2'),
    * -'key1=value1& key2  =' returns array('key1' => 'value1', 'key2' => ''),
    * -'key1=value1   & key2  ' returns array('key1' => 'value1', 'key2' => null),
    * -'any value' with $delim1 = '' returns false.
    * 
    * @param string $req The request string
    * @param string $delim1 The connector string between key-value pairs
    * @param string $delim2 The connector string between a key and a value
    * 
    * @return boolean|string[]
    */
   public static function splitRequest($req, $delim1 = '&', $delim2 = '=') {
      if (!\is_string($req) || (($req = \trim($req)) == ''))
         return false;
      $tmp = \explode($delim1, $req);
      if (!\is_array($tmp) || (\count($tmp) <= 0))
         return false;
      $arr = array();
      foreach ($tmp as $key=>$value) {
         $i = \strpos($value, $delim2);
         if ($i !== false) {
            $key = \trim(\substr($value, 0, $i));
            $tmp = \substr($value, $i + 1);
            $value = ($tmp === false)? '': \trim($tmp);
         } else {
            $key = \trim($value);
            $value = null;
         }
         if ($key !== '')
            $arr[$key] = $value;
      }
      return $arr;
   }
}
