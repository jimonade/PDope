# PDope
php pdo practical extensions for simple 1-table mvc models

this helper class is required in your model's base class 
```
class DataProperty {
  public $name;
  private $type;
  public $required;

  function __construct($name, $type=NULL, $required=FALSE) {
    $this->name = $name;
    if (empty($type)) {
      $type = "STRING";
    }
    $this->type = $type;
    $this->required = $required;
  }
  public function set_type($type) {
    $this->type = strtoupper($type);
  }
  public function get_type() {
    return $this->type;
  }
}
```

first, define data properties in your model constructor
```
  function __construct($props=null) {
    parent:: __construct($props);

    $this->define_data_property("id", "STRING", TRUE);
    $this->define_data_property("title", "STRING", FALSE);
    $this->define_data_property("description", "STRING", FALSE);
    $this->define_data_property("organization_id", "STRING", FALSE);
    $this->define_data_property("address_line_1", "STRING", FALSE);
    $this->define_data_property("address_line_2", "STRING", FALSE);
    $this->define_data_property("address_line_3", "STRING", FALSE);
    $this->define_data_property("city", "STRING", FALSE);
    $this->define_data_property("state", "STRING", FALSE);
    $this->define_data_property("zip", "STRING", FALSE);
    $this->define_data_property("capacity", "INT", FALSE);
    $this->define_data_property("latitude", "FLOAT", FALSE);
    $this->define_data_property("longitude", "FLOAT", FALSE);
    $this->define_data_property("created_at", "DATE", FALSE);
    $this->define_data_property("updated_at", "DATE", FALSE);
    $this->define_data_property("active", "BOOL", FALSE);
  }
```

example, simple query
```
  $db = new \PDope\Statement("select", "facility", $this);
  $db->add_where_parameters_auto(TRUE);
  return $db->execute();
```

example, simple insert
```
  $db = new \PDope\Statement("insert", "facility", $this);
  $db->add_parameters_auto(TRUE);
  $db->add_parameter("id", "UUID");
  $db->add_parameter("created_at", "NOW");
  $db->add_parameter("updated_at", "NOW");
  $db->execute();
```

example, simple update
```
  $db = new \PDope\Statement("update", "facility", $this);
  $db->add_parameters_auto(TRUE);

  //we dont want to update "id" or "created_at"
  $db->remove_parameter("id");
  $db->remove_parameter("created_at");

  $db->add_parameter("updated_at", "NOW");
  $db->add_where_parameter("id", "STRING");
  $db->execute();
```

example, simple suspend
```
  $db = new \PDope\Statement("update", "facility", $this);
  $db->add_parameter("updated_at", "NOW");
  $db->add_parameter("active");
  $db->add_where_parameter("id");
  $db->execute();
```

example, you can always just use the pdo object to build your own statement from scratch
```
$pdo = \PDope\Connection:: connection();
$statement = $pdo->prepare("SELECT property FROM table WHERE id = :id");
$statement->execute(['id' => $model->id]);
$results = $statement->fetchAll(\PDO::FETCH_OBJ);
```
