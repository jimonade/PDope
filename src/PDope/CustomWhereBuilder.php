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
    $this->where_lines = array();
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

    $this->where_lines[] = $this->build_where($rule);
    // error_log("PDopeCustomWhereBuilder->add_where_rule() added: \n" . print_r($rule, TRUE)); 
  }

  /**
  * adds a where rule to the rule collection
  *
  * @example
  * <code>
  *   $where = new \PDope\CustomWhereBuilder($db);
  *   $where->add_where_rule("AND", "id", "IN", $unique_events, "STRING");
  *   $where->add_where_rule("AND", "active", "=", "1", "BOOL");
  * </code>
  *
  * @return  VOID
  *
  * @since   2016-09-28
  * @author  Wesley Dekkers <wesley@sdicg.com>
  **/
  public function add_where_rule_contained($container_rules, $add_to_where = TRUE) {
    $sql = "";
    $length = count($container_rules);
    $i = 0;
    foreach($container_rules as $new_rule){
      $rule = new \PDope\WhereRule($new_rule[0], $new_rule[1], $new_rule[2], $new_rule[3], $new_rule[4]);
        $this->rules[] = $rule;
      $i++;

      if(empty($sql)){
        // When first statement open it
        $sql .= " ".$new_rule[0]." ( ";          
        // The second argument is false because we do not want a AND or a OR directly after (   
        $sql .= $this->build_where($rule, false); 
      }else{
        $sql .= $this->build_where($rule);
      }

      // If this was last statement close it
      if($i == $length){
        $sql .= " ) ";
      }
    }

    // When add to where add it
    if($add_to_where){
      $this->where_lines[] = $sql;
    }

    return $sql;
  }

  /**
  * contains statements together
  *
  * @example
  * <code>
  * $compound = array();
  *
  * $contain = array();
  * $contain[] = array("", "start", "BETWEEN", array($start,$end), "ISO8601");
  * $contain[] = array("OR", "end", "BETWEEN", array($start,$end), "ISO8601");
  * $compound[] = $where->add_where_rule_contained($contain, FALSE);
  *
  * $contain = array();
  * $contain[] = array("OR", "start", "<=", "{$start}", "ISO8601");
  * $contain[] = array("AND", "end", ">=", "{$end}", "ISO8601");
  * $compound[] = $where->add_where_rule_contained($contain, FALSE);
  *
  * // Merge the statement in a compound
  * $where->compound_statement($compound, 'AND');
  * </code>
  *
  * @return  VOID
  *
  * @since   2016-09-28
  * @author  Wesley Dekkers <wesley@sdicg.com>
  **/
  public function compound_statement($statements, $verb){
    $sql = "\n  ".$verb;
    $sql .= "\n ( ";
    foreach($statements as $statement){
      $sql .= "\n ".$statement;
    }
    $sql .= "\n ) ";

    $this->where_lines[] = $sql;
  } 

  /**
  * Build a proper WHERE and return it
  *
  * @example
  * <code>
  * $array = ($verb, $field, $operator, $value, $type);
  * $this->build_where($array);
  * </code>
  *
  * @return  VOID
  *
  * @since   2016-09-28
  * @author  Jim Harney <jim@schooldatebooks.com>
  * @author  Wesley Dekkers <wesley@sdicg.com>
  **/
  public function build_where($rule, $verb = true){
    $field = \PDope\Utilities:: escape_mysql_identifier($rule->field);
        $operator = $rule->operator;
        $token = $rule->token;

        if(is_object($rule)){
        $field = \PDope\Utilities:: escape_mysql_identifier($rule->field);
        $operator = $rule->operator;
        $token = $rule->token;
        if($verb){
          $sql .= " {$rule->verb} ";
        }

        if(is_array($token) && $operator == 'BETWEEN'){
          if (\PDope\Utilities:: is_special_type($rule->type)) {
            throw new \Exception("PDopeCustomWhereBuilder get_where(), array, does not support special type [{$rule->type}]");
          }

          $token_list = implode(", ", $token);
          $sql .= " $field $operator {$token[0]} AND {$token[1]}";
        }
        elseif (is_array($token)) {

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

      return $sql;
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

    foreach($this->where_lines as $where_line){
      $sql .= " ".$where_line;
    }
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