<?php
namespace PDope;

/**
* PDopeParameter represents the data structure of a paramater
*
* @since   2016-5-21
* @author  Jim Harney <jim@schooldatebooks.com>
* @author  Deac Karns <deac@sdicg.com>
**/
class Parameter {
  public $name;
  public $type;
  function __construct($name, $type) {
    $this->name = $name;
    $this->type = strtoupper($type);
  }
}