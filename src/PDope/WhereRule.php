<?php
namespace PDope;

/**
* WhereRule represents the data structure of a sql where clause rule
*
* @since   2016-5-21
* @author  Jim Harney <jim@schooldatebooks.com>
**/
class WhereRule {
  public $verb;
  public $field;
  public $operator;
  public $value;
  public $type;
  public $token;

  function __construct($verb, $field, $operator, $value, $type) {
    $this->verb = strtoupper($verb);
    $this->field = $field;
    $this->operator = strtoupper($operator);
    $this->type = strtoupper($type);
    $this->value = $value;

    //if $value is an array, we also need an array of tokens
    if (is_array($value)) {
      $this->token = array();
      for ($i=0; $i < count($value); $i++) { 
        $this->token[] = self:: get_token("{$i}_");
      }
    } else {
      $this->token = self:: get_token();
    }
  
    // error_log("WhereRule->new() this is: \n" . print_r($this, TRUE)); 
  }

  /**
  * binds special types (NOW, NULL)
  *
  * @example
  * <code>
  * $this->bind_special_parameters();
  * </code>
  *
  * @return  VOID
  *
  * @since   2016-5-21
  * @author  Jim Harney <jim@schooldatebooks.com>
  **/
  private static function get_token($prefix="") {

    return ":t_{$prefix}".sprintf( '%04x%04x%04x%04x%04x%04x%04x%04x',
      // 32 bits for "time_low"
      mt_rand( 0, 0xffff ), mt_rand( 0, 0xffff ),

      // 16 bits for "time_mid"
      mt_rand( 0, 0xffff ),

      // 16 bits for "time_hi_and_version",
      // four most significant bits holds version number 4
      mt_rand( 0, 0x0fff ) | 0x4000,

      // 16 bits, 8 bits for "clk_seq_hi_res",
      // 8 bits for "clk_seq_low",
      // two most significant bits holds zero and one for variant DCE1.1
      mt_rand( 0, 0x3fff ) | 0x8000,

      // 48 bits for "node"
      mt_rand( 0, 0xffff ), mt_rand( 0, 0xffff ), mt_rand( 0, 0xffff )
    );
  }

}