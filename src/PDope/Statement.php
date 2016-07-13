<?php
namespace PDope;

/**
* PDO provides parameterized pdo statements for use with simple one-table mvc models
*
* @since   2016-5-21
* @author  Jim Harney <jim@schooldatebooks.com>
* @author  Deac Karns <deac@sdicg.com>
**/
class Statement {

  private $debug;
  private $pdo;

  private $sql_verb;
  private $table_name;
  private $model_object;

  private $parameters;
  private $where_parameters;
  private $used_custom_where;
  private $custom_where_rules;

  private $sql;
  private $sql_where;

  private $statement;

  function __construct($sql_verb, $table_name, $model_object) {
    $this->debug = FALSE; 

    $this->pdo = \PDope\Connection:: connection();

    $this->sql_verb = strtoupper($sql_verb);
    $this->check_verb();
    $this->table_name = \PDope\Utilities:: escape_mysql_identifier($table_name);
    $this->model_object = $model_object;

    $this->parameters = array();
    $this->where_parameters = array();
    $this->used_custom_where = FALSE;
    $this->custom_where_rules = NULL;

    $this->sql = "";
    $this->sql_where = "";
  }

  /**
  * enables debug output
  *
  * @example
  * <code>
  * $pdo->set_debug(TRUE);
  * </code>
  *
  * @return  VOID
  *
  * @since   2016-5-21
  * @author  Jim Harney <jim@schooldatebooks.com>
  * @author  Deac Karns <deac@sdicg.com>
  **/
  public function set_debug($b) {
    $this->debug=$b;
  }

  /**
  * logs info depending on the $this->debug flag
  *
  * @example
  * <code>
  * $this->log_debug("a message", $object);
  * </code>
  *
  * @return  VOID
  *
  * @since   2016-5-21
  * @author  Jim Harney <jim@schooldatebooks.com>
  **/
  private function log_debug($string_message, $object_to_dump=NULL) {
    if ($this->debug) {
      if (!empty($object_to_dump)) {
        error_log("$string_message \n" . print_r($object_to_dump, TRUE));   
        error_log(""); //this fixes the spooky "php log breaks jasmine tests" problem
      } else {
        error_log("$string_message");   
      }
    }
  }  

  /**
  * calls add_parameter() for each "data property" in the model_object
  *
  * @example
  * <code>
  * $pdo->add_parameters_auto(TRUE); //require issset checks on model_object
  * $pdo->add_parameters_auto(FALSE); //do not require issset checks on model_object
  * </code>
  *
  * @return  VOID
  *
  * @since   2016-5-21
  * @author  Jim Harney <jim@schooldatebooks.com>
  **/
  public function add_parameters_auto($require_isset=FALSE) {
    foreach ($this->model_object->get_data_properties($require_isset) as $property) {
      $this->add_parameter($property->name, $property->get_type());
    }   
  }

  /**
  * add a PDopeParameter object to the parameter collection
  *
  * @example
  * <code>
  * $pdo->add_parameter("id", "STRING");
  * $pdo->add_parameter("active", "BOOLEAN");
  * </code>
  *
  * @return  VOID
  *
  * @since   2016-5-21
  * @author  Jim Harney <jim@schooldatebooks.com>
  **/
  public function add_parameter($name, $type=NULL) {

    //if we are not given a type, look it up in model_object
    if (empty($type)) {
      $type = $this->model_object->get_data_property($name)->get_type();
    }
    $value = $this->model_object->$name;

    $this->log_debug("add_parameter() name [$name], type [$type], value [$value]");

    // translate all empty values to special DB NULL type
    if (
      (empty($value) && !is_string($value))
      && (!is_bool($value))
      && ($type != "UUID")
      && ($type != "NOW")
    ) {
      $type = "NULL";
    }

    //if parameter already exists, then overwrite it
    $exists=FALSE;
    foreach($this->parameters as $parameter) {
      if ($parameter->name == $name) {
        // $this->log_debug("add_parameter() overwriting existing parameter name [$name], type [$type]");
        $exists = TRUE;
        $parameter->type = $type;
      }
    }

    if (!$exists) {
      // $this->log_debug("add_parameter() adding parameter name [$name], type [$type]");
      $parameter = new \PDope\Parameter($name, $type);
      $this->parameters[] = $parameter;
    }
  }

  /**
  * removes a PDopeParameter object from the parameter collection
  *
  * @example
  * <code>
  * $pdo->remove_parameter("id");
  * </code>
  *
  * @return  VOID
  *
  * @since   2016-5-21
  * @author  Jim Harney <jim@schooldatebooks.com>
  **/
  public function remove_parameter($name) {
    $clean_parameters = array();
    for ($i=0; $i < count($this->parameters); $i++) { 
      $parameter = $this->parameters[$i];
      if ($parameter->name != $name) {
        $clean_parameters[] = $parameter;
      }
    }
    $this->parameters = $clean_parameters;
    // $this->log_debug("this->parameters is: \n", $this->parameters); 
  }  

  /**
  * calls add_where_parameter() for each "data property" in the model_object
  *
  * @example
  * <code>
  * $pdo->add_where_parameters_auto(TRUE); //require issset checks on model_object
  * $pdo->add_where_parameters_auto(FALSE); //do not require issset checks on model_object
  * </code>
  *
  * @return  VOID
  *
  * @since   2016-5-21
  * @author  Jim Harney <jim@schooldatebooks.com>
  **/
  public function add_where_parameters_auto($require_isset=FALSE) {
    foreach ($this->model_object->get_data_properties($require_isset) as $property) {
      $this->add_where_parameter($property->name, $property->get_type());
    }   
  }

  /**
  * add a PDopeParameter object to the "where parameter" collection
  *
  * @example
  * <code>
  * $pdo->add_where_parameter("id", "STRING");
  * $pdo->add_where_parameter("active", "BOOLEAN");
  * </code>
  *
  * @return  VOID
  *
  * @since   2016-5-21
  * @author  Jim Harney <jim@schooldatebooks.com>
  **/
  public function add_where_parameter($name, $type=NULL) {

    //if we used a custom where clause, we do not need to add these parameters
    if ($this->used_custom_where) {
      return;
    }

    //if we are not given a type, look it up in model_object
    if (empty($type)) {
      $type = $this->model_object->get_data_property($name)->get_type();
    }
    // $this->log_debug("add_where_parameter() name [$name], type [$type]");

    //if parameter already exists, then overwrite it
    $exists=FALSE;
    foreach($this->where_parameters as $parameter) {
      if ($parameter->name == $name) {
        // $this->log_debug("add_where_parameter() overwriting existing parameter name [$name], type [$type]");
        $exists = TRUE;
        $parameter->type = $type;
      }
    }

    if (!$exists) {
      // $this->log_debug("add_where_parameter() adding parameter name [$name], type [$type]");
      $parameter = new \PDope\Parameter($name, $type);
      $this->where_parameters[] = $parameter;
    }
  }

  /**
  * removes a PDopeParameter object from the "where parameter" collection
  *
  * @example
  * <code>
  * $pdo->remove_where_parameter("id");
  * </code>
  *
  * @return  VOID
  *
  * @since   2016-5-21
  * @author  Jim Harney <jim@schooldatebooks.com>
  **/
  public function remove_where_parameter($name) {
    $clean_parameters = array();
    for ($i=0; $i < count($this->where_parameters); $i++) { 
      $parameter = $this->where_parameters[$i];
      if ($parameter->name != $name) {
        $clean_parameters[] = $parameter;
      }
    }
    $this->where_parameters = $clean_parameters;
    // $this->log_debug("this->where_parameters is: \n", $this->where_parameters); 
  }    

  /**
  * sends a parameters array to bind_value
  *
  * @example
  * <code>
  * $this->bind_parameters($this->parameters);
  * $this->bind_parameters($this->where_parameters);
  * </code>
  *
  * @return  VOID
  *
  * @since   2016-5-21
  * @author  Jim Harney <jim@schooldatebooks.com>
  **/
  private function bind_parameters($target_parameters) {

    if (! is_array($target_parameters)) {
      return;
    }

    foreach($target_parameters as $parameter) {

      $name = $parameter->name;
      $value = $this->model_object->$name;
      $type = $parameter->type;
      // $this->log_debug("bind_parameters(), loop values, name [$name], value [$value], type [$type]");

      //skip these special paramater types
      if (in_array($type, array("NOW", "NULL"))) continue;

      //handle this special paramater type
      if ($type == "UUID") {
        $value = \PDope\Utilities:: UUID();

        //also write this back to the model object
        $this->model_object->$name = $value;
      }

      // $this->log_debug("bind_parameters() name [$name], value [$value], type [$type]");

      if (is_array($value)) {
        if (\PDope\Utilities:: is_special_type($type)) {
          throw new \Exception("PDopeStatement bind_parameters(), array, does not support special type [{$type}]");
        }  

        for ($i=0; $i < count($value); $i++) { 
          $list_name = "{$name}_{$i}";
          $list_value = $value[$i];
          $this->bind_value($list_name, $list_value);
        }

      } else {
        if (in_array($type, array("NOW", "NULL"))) continue;

        $this->bind_value($name, $value, $type);
      }
    }
  }

  /**
  * sends the custom where rules array to bind_value
  *
  * @example
  * <code>
  * $this->bind_custom_where_rules();
  * </code>
  *
  * @return  VOID
  *
  * @since   2016-5-21
  * @author  Jim Harney <jim@schooldatebooks.com>
  **/
  private function bind_custom_where_rules() {

    if (! is_array($this->custom_where_rules)) {
      return;
    }

    for ($i=0; $i < count($this->custom_where_rules); $i++) { 
      $rule = $this->custom_where_rules[$i];
      // $this->log_debug("bind_custom_where_rules() rule is: \n", $rule); 

      if (is_array($rule->value)) {
        if (\PDope\Utilities:: is_special_type($rule->type)) {
          throw new \Exception("PDopeCustomWhereBuilder bind_custom_where_rules(), array, does not support special type [{$rule->type}]");
        }        
        for ($j=0; $j < count($rule->value); $j++) { 
          $this->bind_value($rule->token[$j], $rule->value[$j], $rule->type);
        }
      } else {
        if (\PDope\Utilities:: is_special_type($rule->type)) {
          continue;
        }
        $this->bind_value($rule->token, $rule->value, $rule->type);
      }

    }
  }

  /**
  * binds a value to a parameter, this wraps PDO's bindValue method
  *
  * @example
  * <code>
  * $this->bind_value($name, $value, $type);
  * </code>
  *
  * @return  VOID
  *
  * @since   2016-5-21
  * @author  Jim Harney <jim@schooldatebooks.com>
  **/
  public function bind_value($name, $value, $type="STRING") {

    // $this->log_debug("bind_value() name: [$name], value: \n", $value); 

    $token = \PDope\Utilities:: format_token($name);

    $type = strtoupper($type);

    if (isset($type)) {

      switch ($type) {

        case \PDO::PARAM_BOOL:
        case "BOOLEAN":
        case "BOOL":
        case "B":
          $value = (boolean)$value;
          break;

        case \PDO::PARAM_INT:
        case "INTEGER":
        case "INT":
        case "I":
          $value = (int)$value;
          break;

        case "DECIMAL":
        case "DEC":
        case "D":
        case "FLOAT":
        case "F":
          $value = (float)$value;
          break;

        // default:
        //   throw new \Exception("get_pdo_type_from_generic_type() Unknown type [$type]");
      }

      $type = \PDope\Utilities:: get_pdo_type_from_generic_type($type);

      $this->log_debug("bind_value() token [$token], value [$value], type [$type]");
      $this->statement->bindValue($token, $value, $type);

    } else {
      $this->log_debug("bind_value() token [$token], value [$value]");
      $this->statement->bindValue($token, $value);
    }
    
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
  private function bind_special_parameters() {
    foreach($this->parameters as $parameter) {
      if (\PDope\Utilities:: is_special_type($parameter->type)) {
        $token = \PDope\Utilities:: format_token($parameter->name);

        if ($parameter->type == "NOW") {
          $this->sql = str_replace($token, "NOW()", $this->sql);
        } elseif ($parameter->type == "NULL") {
          $this->sql = str_replace($token, "NULL", $this->sql);
        }
      }
    }
  }

  /**
  * abstracts bind_parameters($this->where_parameters) vs calling bind_custom_where_rules()
  *
  * @example
  * <code>
  * $this->bind_where_parameters();
  * </code>
  *
  * @return  VOID
  *
  * @since   2016-5-21
  * @author  Jim Harney <jim@schooldatebooks.com>
  **/
  private function bind_where_parameters() {
    if ($this->used_custom_where) {
      $this->bind_custom_where_rules();
    } else {
      $this->bind_parameters($this->where_parameters);
    }
  }

  /**
  * cheks the sql verb
  *
  * @example
  * <code>
  * $this->check_verb();
  * </code>
  *
  * @return  VOID
  *
  * @since   2016-5-21
  * @author  Jim Harney <jim@schooldatebooks.com>
  **/
  private function check_verb() {
    if (! in_array($this->sql_verb, array("SELECT", "UPDATE", "INSERT", "DELETE"))) {
      throw new \Exception("sql query can't operate with verb [{$this->sql_verb}]");
    }
  }

  /**
  * builds the sql text for the pdo statement
  *
  * @example
  * <code>
  * $this->build_statement();
  * </code>
  *
  * @return  VOID
  *
  * @since   2016-5-21
  * @author  Jim Harney <jim@schooldatebooks.com>
  **/
  private function build_statement() {
    switch ($this->sql_verb) {
      case 'SELECT':
        $this->build_select_sql();
        $this->build_where_sql();
        break;
      case 'UPDATE':
        $this->build_update_sql();
        $this->build_where_sql();
        break;
      case 'INSERT':
        $this->build_insert_values_sql();
        break;
      case 'DELETE':
        $this->build_delete_sql();
        $this->build_where_sql();
        break;
      default:
        throw new \Exception("sql query can't operate with verb [{$this->sql_verb}]");
    }
  }

  /**
  * executes a parameterized pdo statement
  *
  * @example
  * <code>
  * $pdo->execute();
  * </code>
  *
  * @return  VOID or array
  *
  * @since   2016-5-21
  * @author  Jim Harney <jim@schooldatebooks.com>
  * @author  Deac Karns <deac@sdicg.com>
  **/
  public function execute() {

    $this->build_statement();
    $this->bind_special_parameters();

    $this->log_debug("execute() this->sql_verb [{$this->sql_verb}] sql is \n{$this->sql}{$this->sql_where}");

    switch ($this->sql_verb) {
      case 'SELECT':
        $this->statement = $this->pdo->prepare("{$this->sql}{$this->sql_where}");
        $this->bind_where_parameters();      
        $this->statement->execute();
        $results = $this->statement->fetchAll(\PDO::FETCH_OBJ);
        $this->log_debug("execute() found [".count($results)."] results", $results); 
        return $results;
        break;
      case 'UPDATE':
        $this->statement = $this->pdo->prepare("{$this->sql}{$this->sql_where}");
        $this->bind_parameters($this->parameters);
        $this->bind_where_parameters();
        $this->statement->execute();
        break;
      case 'INSERT':
        $this->statement = $this->pdo->prepare($this->sql);
        $this->bind_parameters($this->parameters);
        $this->statement->execute();
        break;
      case 'DELETE':
        $this->statement = $this->pdo->prepare("{$this->sql}{$this->sql_where}");
        $this->bind_where_parameters();
        $this->statement->execute();
        break;
      default:
        throw new \Exception("sql query can't operate with verb [{$this->sql_verb}]");
    }
  }

  /**
  * builds the "SELECT" clause
  *
  * @example
  * <code>
  * $this->build_select_sql();
  * </code>
  *
  * @return  VOID
  *
  * @since   2016-5-21
  * @author  Jim Harney <jim@schooldatebooks.com>
  **/
  private function build_select_sql() {
    
    $sql = "SELECT \n";

    $data_properties = $this->model_object->data_properties;

    for ($i=0; $i < count($data_properties); $i++) { 
      $property = $data_properties[$i];
      $name = $property->name;

      if ($i == 0) {
        $sql .= "\t  ";
      } else {
        $sql .= "\t, ";
      }

      $sql .= "{$name} \n";
    }

    $sql .= "FROM \n\t{$this->table_name} \n";

    $this->sql .= $sql;

    // $this->log_debug("build_select_sql() built \n$sql");
  }

  /**
  * builds the "UPDATE" clause
  *
  * @example
  * <code>
  * $this->build_update_sql();
  * </code>
  *
  * @return  VOID
  *
  * @since   2016-5-21
  * @author  Jim Harney <jim@schooldatebooks.com>
  **/
  private function build_update_sql() {
    $sql = "UPDATE \n\t{$this->table_name} \n";

    $sql .= "SET \n";

    for ($i=0; $i < count($this->parameters); $i++) { 
      $parameter = $this->parameters[$i];

      if ($i == 0) {
        $sql .= "\t  ";
      } else {
        $sql .= "\t, ";
      }

      $sql .= \PDope\Utilities:: escape_mysql_identifier($parameter->name)." = :{$parameter->name} \n";
    }

    $this->sql .= $sql;

    // $this->log_debug("build_update_sql() built \n$sql");
  }

  /**
  * builds the "DELETE" clause
  *
  * @example
  * <code>
  * $this->build_delete_sql();
  * </code>
  *
  * @return  VOID
  *
  * @since   2016-5-21
  * @author  Jim Harney <jim@schooldatebooks.com>
  **/
  private function build_delete_sql() {
    $sql = "DELETE FROM {$this->table_name} \n";

    $this->sql .= $sql;

    // $this->log_debug("build_update_sql() built \n$sql");
  }  

  /**
  * builds the "WHERE" clause
  *
  * @example
  * <code>
  * $this->build_where_sql();
  * </code>
  *
  * @return  VOID
  *
  * @since   2016-5-21
  * @author  Jim Harney <jim@schooldatebooks.com>
  **/
  private function build_where_sql() {

    //if we used a custom where clause, we do not need to build it
    if ($this->used_custom_where || count($this->where_parameters) < 1) {
      return;
    }

    $sql = "WHERE \n";

    for ($i=0; $i < count($this->where_parameters); $i++) { 
      $parameter = $this->where_parameters[$i];

      if ($i == 0) {
        $sql .= "\t";
      } else {
        $sql .= "\tAND ";
      }

      $name = $parameter->name;
      $type = $parameter->type;
      $value = $this->model_object->$name;

      if (is_array($value)) {

        if (\PDope\Utilities:: is_special_type($type)) {
          throw new \Exception("PDopeStatement build_where_sql(), array, does not support special type [{$type}]");
        }

        $sql .= "(".\PDope\Utilities:: escape_mysql_identifier($name)." IN (";

        for ($j=0; $j < count($value); $j++) { 
          if ($j > 0) {
            $sql .= ", ";
          }

          $token = "{$name}_{$j}";
          $token = \PDope\Utilities:: format_token($token);

          $sql .= $token;
        }

        $sql .= "))";
      } else {
        if (\PDope\Utilities:: is_special_type($type)) {
          $token = \PDope\Utilities:: translate_special_token($name, $type);  
          $sql .= "(".\PDope\Utilities:: escape_mysql_identifier($name)." = $token) \n";  
        } else {
          $token = \PDope\Utilities:: format_token($name);
          $sql .= "(".\PDope\Utilities:: escape_mysql_identifier($name)." = $token) \n";
        }
      }

    }

    $this->sql_where .= $sql;

    // $this->log_debug("build_where_sql() built \n$sql");
  }

  /**
  * builds the "INSERT" clause
  *
  * @example
  * <code>
  * $this->build_insert_values_sql();
  * </code>
  *
  * @return  VOID
  *
  * @since   2016-5-21
  * @author  Jim Harney <jim@schooldatebooks.com>
  **/
  private function build_insert_values_sql() {
    
    $fields_sql = "";
    $values_sql = "";

    for ($i=0; $i < count($this->parameters); $i++) { 
      $parameter = $this->parameters[$i];

      if ($i == 0) {
        $fields_sql .= "\t  ";
        $values_sql .= "\t  ";
      } else {
        $fields_sql .= "\t, ";
        $values_sql .= "\t, ";
      }

      $fields_sql .= \PDope\Utilities:: escape_mysql_identifier($parameter->name)." \n";
      $values_sql .= ":{$parameter->name} \n";
    }

    $sql = "INSERT INTO {$this->table_name} \n";
    $sql .= "( \n";
    $sql .= $fields_sql;
    $sql .= ") \n";    

    $sql .= "VALUES \n";
    $sql .= "( \n";
    $sql .= $values_sql;
    $sql .= ") \n";  

    $this->sql .= $sql;

    // $this->log_debug("build_insert_values_sql() built \n$sql");    
  }

  /**
  * triggers the use of a PDopeStatementCustomWhereBuilder object
  *
  * @example
  * <code>
  * $custom_where = new \PDope\StatementCustomWhereBuilder($db);
  * $custom_where->add_where_rule("AND", "id", "=", "facility_1", "STRING");
  * // $custom_where->add_where_rule("AND", "id", "IN", array("facility_1", "facility_2", "facility_3"), "STRING");
  * $custom_where->add_where_rule("AND", "active", "=", "1", "BOOL");
  * $db->use_custom_where($custom_where);
  * </code>
  *
  * @return  VOID
  *
  * @since   2016-5-21
  * @author  Jim Harney <jim@schooldatebooks.com>
  **/
  public function use_custom_where($custom_where) {
    $this->used_custom_where = TRUE;
    $this->where_parameters = NULL;
    $this->sql_where = $custom_where->get_where();
    $this->custom_where_rules = $custom_where->get_rules();
  }

  /**
  * allows use of a raw SQL where clause to account for things like grouping
  * 
  * @example
  * <code>
  * $where = "WHERE (x = 1 OR x = 2) AND (y = 3 OR y = 4)"
  * $db->use_raw_where($where)
  * </code>
  * 
  * @return VOID
  * 
  * @since 2016-7-12
  * @author Matthew Ess <matthew@schooldatebooks.com>
  **/
  public function use_raw_where($custom_where) {
    $this->used_custom_where = TRUE;
    $this->where_parameters = NULL;
    $this->sql_where = $custom_where;
    $this->custom_where_rules = NULL;
  }

}

