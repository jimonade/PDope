<?php
namespace PDope;

/**
* PDopeConnection provides a static pdo connection object
*
* @since   2016-5-21
* @author  Jim Harney <jim@schooldatebooks.com>
* @author  Deac Karns <deac@sdicg.com>
**/
class Connection {

  static private $pdo;

  /**
  * initialize a pdo connection
  *
  * @example
  * <code>
  * \PDope\Connection:: connect();
  * </code>
  *
  * @return  VOID
  *
  * @since   2016-5-21
  * @author  Jim Harney <jim@schooldatebooks.com>
  **/
  public function connect($config){
    $dsn="mysql:host={$config->host};port={$config->port};dbname={$config->database};charset=utf8";

    $pdo = new \PDO($dsn, $config->user, $config->password);

    $pdo->setAttribute(\PDO:: ATTR_ERRMODE, \PDO:: ERRMODE_EXCEPTION);

    self:: $pdo = $pdo;
  }

  /**
  * get a reference to the static pdo connection object
  *
  * @example
  * <code>
  * $pdo_connection = \PDope\Connection:: connection();
  * </code>
  *
  * @return  VOID
  *
  * @since   2016-5-21
  * @author  Jim Harney <jim@schooldatebooks.com>
  **/
  public function connection() {
    if(empty(self:: $pdo)){
      throw new \Exception("No connection found. you must run connect() first.", 1);
    }
    return self:: $pdo;
  }

}