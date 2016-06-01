<?php
namespace PDope;

/**
* Utilities provides shared pdo utilities
*
* @since   2016-5-21
* @author  Jim Harney <jim@schooldatebooks.com>
**/
class Utilities {

  /**
  * determine if a type is special
  *
  * @example
  * <code>
  * $check = \PDope\Utilities:: is_special_type($type);
  * </code>
  *
  * @return  boolean
  *
  * @since   2016-5-21
  * @author  Jim Harney <jim@schooldatebooks.com>
  **/
  public static function is_special_type($type) {
    $special_types = array("NOW", "NULL", "UUID");

    if (in_array($type, $special_types)) {
      return TRUE;
    }
    return FALSE;
  }

  /**
  * format a token, ensure it begins with ":"
  *
  * @example
  * <code>
  * $token = \PDope\Utilities:: format_token($name);
  * </code>
  *
  * @return  string
  *
  * @since   2016-5-21
  * @author  Jim Harney <jim@schooldatebooks.com>
  **/
  public static function format_token($token) {
    if (substr($token, 0, 1) != ":") {
      $token = ":{$token}";
    }
    return $token;
  }

  // public static function quote($value, $type="S") {
  //   $pdo = \PDope\Connection:: connection();
  //   $type = self:: get_pdo_type_from_generic_type($type);
  //   return $pdo->quote($value, $type);
  // }

  /**
  * escape a mysql identifier
  *
  * @example
  * <code>
  * $table_name = \PDope\Utilities:: escape_mysql_identifier($table_name);
  * $field_name = \PDope\Utilities:: escape_mysql_identifier($field_name);
  * </code>
  *
  * @return  string
  *
  * @since   2016-5-21
  * @author  Jim Harney <jim@schooldatebooks.com>
  **/
  public static function escape_mysql_identifier($table_name) {
    return "`".str_replace("`", "``", $table_name)."`";
  }

  /**
  * translate a token based on special type
  *
  * @example
  * <code>
  * $token = \PDope\Utilities:: translate_special_token($name, $type);  
  * </code>
  *
  * @return  string
  *
  * @since   2016-5-21
  * @author  Jim Harney <jim@schooldatebooks.com>
  **/
  public static function translate_special_token($token, $type) {
    if ($type == "NOW") {
      $token = "NOW()";
    } elseif ($type == "NULL") {
      $token = "NULL";
    } else {
      $token = self:: format_token($token);
    }
    return $token;
  }

  /**
  * translates a generic type to a PDO enum type, see http://php.net/manual/en/pdo.constants.php
  *
  * @example
  * <code>
  * $type = \PDope\Utilities:: get_pdo_type_from_generic_type($type);
  * </code>
  *
  * @return  string or int or NULL
  *
  * @since   2016-5-21
  * @author  Jim Harney <jim@schooldatebooks.com>
  **/
  public static function get_pdo_type_from_generic_type($type) {
    $type = strtoupper($type);
    $new_type = NULL;
    switch ($type) {

      case \PDO::PARAM_STR:
      case "STRING":
      case "STR":
      case "S":
        $new_type = \PDO::PARAM_STR;
        break;

      case \PDO::PARAM_BOOL:
      case "BOOLEAN":
      case "BOOL":
      case "B":
        $new_type = \PDO::PARAM_BOOL;
        break;

      case \PDO::PARAM_INT:
      case "INTEGER":
      case "INT":
      case "I":
        $new_type = \PDO::PARAM_INT;
        break;

      case "DECIMAL":
      case "DEC":
      case "D":
      case "FLOAT":
      case "F":
        $new_type = \PDO::PARAM_STR;
        break;

      // default:
      //   throw new \Exception("get_pdo_type_from_generic_type() Unknown type [$type]");
    }
    return $new_type;
  }  

  /**
  * creates a uuid string
  * thanks rhonda https://github.com/peledies/rhonda/blob/master/src/Rhonda/UUID.php
  *
  * @example
  * <code>
  * $value = \PDope\Utilities:: UUID();
  * </code>
  *
  * @return  string
  *
  * @since   2016-5-21
  * @author  Deac Karns <deac@sdicg.com>
  * @author  Jim Harney <jim@schooldatebooks.com>
  **/
  public static function UUID() {
    return sprintf( '%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
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