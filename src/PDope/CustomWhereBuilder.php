<?php
namespace PDope;

/**
* PDopeCustomWhereBuilder provides helpers to build simple dynamic sql where clauses
*
* @since   2016-5-21
* @author  Jim Harney <jim@schooldatebooks.com>
**/
class CustomWhereBuilder {
  private $pdo;
  private $rules;

  function __construct($pdo) {
    $this->pdo = $pdo;
    $this->rules = array();
  }

  /**
  * adds a where rule to the rule collection
  *
  * @example
  * <code>
  * $custom_where->add_where_rule("AND", "id", "=", "12345", "s");
  * </code>
  *
  * @return  VOID
  *
  * @since   2016-5-21
  * @author  Jim Harney <jim@schooldatebooks.com>
  **/
  //  
  public function add_where_rule($verb, $field, $operator, $value, $type) {

    $rule = new \PDope\WhereRule($verb, $field, $operator, $value, $type);
    $this->rules[] = $rule;

    // error_log("PDopeCustomWhereBuilder->add_where_rule() added: \n" . print_r($rule, TRUE)); 
  }

  /**
  * builds sql text representation of the rule collection
  *
  * @example
  * <code>
  * $sql_where = $custom_where->get_where();
  * </code>
  *
  * @return  string
  *
  * @since   2016-5-21
  * @author  Jim Harney <jim@schooldatebooks.com>
  **/
  public function get_where() {
    $sql = "\nWHERE";

    // error_log("get_where() count is: ".count($this->rules));

    for ($i=0; $i < count($this->rules); $i++) { 
      $rule = $this->rules[$i];

      if ($i == 0) {
        $sql .= "\n\t";
      } else {
        $sql .= "\n\t{$rule->verb} ";
      }

      $field = \PDope\Utilities:: escape_mysql_identifier($rule->field);
      $operator = $rule->operator;
      $token = $rule->token;

      if (is_array($token)) {

        if (\PDope\Utilities:: is_special_type($rule->type)) {
          throw new \Exception("PDopeCustomWhereBuilder get_where(), array, does not support special type [{$rule->type}]");
        }

        $token_list = implode(", ", $token);
        $sql .= "($field $operator ($token_list))";
      } else {
        $token = \PDope\Utilities:: translate_special_token($token, $rule->type);
        $sql .= "($field $operator $token)";  
      }
      
    }

    // error_log("PDopeCustomWhereBuilder->get_where() built: \n{$sql}"); 
    return $sql;
  }

  /**
  * getter for rules collection
  *
  * @example
  * <code>
  * $custom_where_rules = $custom_where->get_rules();
  * </code>
  *
  * @return  array of PDopeWhereRule objects
  *
  * @since   2016-5-21
  * @author  Jim Harney <jim@schooldatebooks.com>
  **/
  public function get_rules() {
    return $this->rules;
  }

}