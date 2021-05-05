<?php

namespace Conn\Connection;

use PDO;
use PDOException;

class Connection
{

  public function __construct()
  {
  }

  private function __clone()
  {
  }

  public function __destruct()
  {
    $this->disconnect();
    foreach ($this as $key => $value) {
      unset($this->$key);
    }
  }

  private static $dbtype   = "mysql";
  private static $host     = "127.0.0.1";
  private static $port     = "3308";
  private static $user     = "main";
  private static $password = "1234";
  private static $db       = "MIEEP";

  private function getDBType()
  {
    return self::$dbtype;
  }
  private function getHost()
  {
    return self::$host;
  }
  private function getPort()
  {
    return self::$port;
  }
  private function getUser()
  {
    return self::$user;
  }
  private function getPassword()
  {
    return self::$password;
  }
  private function getDB()
  {
    return self::$db;
  }

  private function connect()
  {
    try {
      $this->connection = new PDO(
        $this->getDBType() . ":host=" .
          $this->getHost() . ";port=" .
          $this->getPort() . ";dbname=" .
          $this->getDB(),
        $this->getUser(),
        $this->getPassword()
      );
    } catch (PDOException $p) {
      die("Erro: <code>" . $p->getMessage() . "</code>");
    }

    return ($this->connection);
  }

  private function disconnect()
  {
    $this->connection = null;
  }

  public function Select($sql, $params = null)
  {
    $query = $this->connect()->prepare($sql);
    $response = null;

    if (!$query->execute($params)) {
      $error = $query->errorInfo();
      return array("error" => true, "message" => "Select failure (" . $error[2] . ")");
    }

    $response = $query->fetchAll(PDO::FETCH_OBJ);

    // $query->execute($params);
    // $query->debugDumpParams();

    if ($query->rowCount() > 0) {
      return array("error" => false, "data" => $response);
    } else {
      return array("error" => true, "message" => "No data");
    }
    $this->__destruct();
  }

  public function Insert($sql, $params = null)
  {
    $connection = $this->connect();
    $query = $connection->prepare($sql);

    try {
      $response = $query->execute($params);

      // $query->execute($params);
      // $query->debugDumpParams();

      if (!$response) {
        $error = $query->errorInfo();
        if ($error[1] == 1062) {
          return array("error" => true, "message" => "Value alredy exists");
        } else {
          return array("error" => true, "message" => "Insert failure (" . $error[2] . ")");
        }
      }
      $lastId = $connection->lastInsertId();

      return array("error" => false, "message" => "Add data success", "last_id" => $lastId);
      //$response = $connection->lastInsertId() or die(print_r($query->errorInfo(), true));



    } catch (PDOException $e) {
      $query->errorInfo();
      echo "error";
      return;
    }
    // $this->__destruct();
    return $response;
  }

  public function Update($sql, $params = null)
  {
    $query = $this->connect()->prepare($sql);

    if ($query->execute($params)) {

      if ($query->rowCount() >= 1) {
        return array("error" => false, "message" => "Update success");
      }
      return array("error" => true, "message" => "No data to update");
    } else {
      $error = $query->errorInfo();
      return array("error" => true, "message" => "Update failure (" . $error[2] . ")");
    }
  }

  public function Delete($sql, $params = null)
  {
    $query = $this->connect()->prepare($sql);
    //$query->execute($params);
    $response = $query->rowCount();

    // $query->execute($params);
    // $query->debugDumpParams();
    // die;

    if ($query->execute($params)) {

      if ($query->rowCount() >= 1) {
        return array("error" => false, "message" => "Delete success");
      }
      return array("error" => true, "message" => "No data to delete");
    } else {
      $error = $query->errorInfo();
      return array("error" => true, "message" => "Delete failure (" . $error[2] . ")");
    }

    $this->__destruct();
    return $response;
  }
}
