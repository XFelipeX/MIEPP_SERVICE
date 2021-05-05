<?php

use Ratchet\MessageComponentInterface;
use Ratchet\ConnectionInterface;
use Exception;

require_once('../../DAO/CCPP/User.php');
require_once('../../DAO/MIEPP/Device.php');

require_once dirname(__FILE__) . '/../../vendor/autoload.php';

class Component implements MessageComponentInterface
{
  protected $connections;

  public function __construct()
  {
    system("clear");
    echo "----------------------" . PHP_EOL;
    echo "Initializing server..." . PHP_EOL;
    try {
      $this->connections = new \SplObjectStorage;
    } catch (Exception $e) {
      echo "Error (__construct): " . $e->getMessage();
    }
    echo "----------------------" . PHP_EOL;
  }

  public function onOpen(ConnectionInterface $conn)
  {
    echo "----------------------" . PHP_EOL;
    echo "New connection..." . PHP_EOL;
    try {
      $this->connections->attach($conn);
      $count = $this->connections->count();
      echo "Count: " . $count . PHP_EOL;
    } catch (Exception $e) {
      echo "Error (onOpen): " . $e->getMessage();
    }
    echo "----------------------" . PHP_EOL;
  }

  public function onMessage(ConnectionInterface $from, $msg)
  {
    try {
      if ($msg == '') {
        echo "----------------------" . PHP_EOL;
        echo 'Empty JSON' . PHP_EOL;
        echo "----------------------" . PHP_EOL;
        $from->send((string)json_encode(array("error" => true, "message" => "(JSON) is broken")));
        return;
      }

      if ($msg == "__ping__") {
        $from->send((string)"__pong__");
      }

      $jsonBody = json_decode($msg, true);

      if (isset($this->connections[$from]) && $this->connections[$from] == null) {
        $this->SetConnection($from, $jsonBody);
        return;
      }

      $type = isset($jsonBody["type"]) ? (int)$jsonBody["type"] : null;
      if ($type === 1) {
        $this->getDevicesOnline($from, $jsonBody);
        return;
      }
    } catch (Exception $e) {
      echo "----------------------" . PHP_EOL;
      echo "Error (onMessage): " . $e->getMessage() . PHP_EOL;
      echo "----------------------" . PHP_EOL;

      $from->send((string)json_encode(array(
        "error" => true,
        "message" => $e->getMessage()
      )));
    }
  }

  public function onClose(ConnectionInterface $conn)
  {
    // Notify users MIEPP
    if ($this->connections[$conn]["imei"]) {
      foreach ($this->connections as $connection) {
        if (isset($this->connections[$connection]["id"]) && $this->connections[$connection]["id"] != null && !isset($this->connections[$connection]["imei"])) {
          $data = array("error" => false, "message" => "Device connection is closed", "object" => $this->connections[$conn]);
          $connection->send(json_encode($data));
        }
      }
    }

    $this->connections->detach($conn);

    echo "----------------------" . PHP_EOL;
    echo "Connection {$conn->resourceId} has disconnected" . PHP_EOL;
    echo "----------------------" . PHP_EOL;
  }

  public function onError(ConnectionInterface $conn, \Exception $e)
  {
    echo "An error has occurred: {$e->getMessage()}\n";

    $conn->close();
  }

  public function SetConnection($conn, $jsonBody)
  {
    if ((!isset($jsonBody['imei']) || !isset($jsonBody['app_id'])) && (!isset($jsonBody["auth"]) || !isset($jsonBody["app_id"]))) {
      echo "----------------------" . PHP_EOL;
      echo "(imei, app_id,auth) is broken" . PHP_EOL;
      echo "End connection..." . PHP_EOL;
      $this->connections->detach($conn);
      $count = $this->connections->count();
      echo "Count: " . $count . PHP_EOL;
      echo "----------------------" . PHP_EOL;
      $conn->send((string)json_encode(array("error" => true, "message" => "(imei, app_id) is broken")));
      return;
    }

    $app_id = (int) $jsonBody['app_id'];
    $imei = isset($jsonBody['imei']) ? $jsonBody['imei'] : null;

    if ($app_id != 6 && $app_id != 5) {
      echo "----------------------" . PHP_EOL;
      echo "(app_id) is broken" . PHP_EOL;
      echo "End connection..." . PHP_EOL;
      $this->connections->detach($conn);
      $count = $this->connections->count();
      echo "Count: " . $count . PHP_EOL;
      echo "----------------------" . PHP_EOL;
      $conn->send((string)json_encode(array("error" => true, "message" => "(app_id) is broken")));
      return;
    }

    // Device MIEPP Connection
    if ($imei && $imei != null) {
      $this->getDeviceConnection($imei, $conn);
    }

    $auth = isset($jsonBody['auth']) ? $jsonBody["auth"] : null;

    // User MIEPP Connection
    if ($auth != null) {
      $this->getUserConnection($auth, $app_id, $conn);
    }
  }

  function getUserConnection($auth, $app_id, $conn)
  {
    $daoUser = new DAOUser();

    $response = $daoUser->SelectSession($auth, $app_id);

    if ($response['error']) {
      if ($response['message'] == "No data") {
        $conn->send((string)json_encode(array(
          "error" => true,
          "message" => "This key is unauthorized SetConnection"
        )));

        echo "----------------------" . PHP_EOL;
        echo "This key is unauthorized: " . $auth . PHP_EOL;
        echo "For Application ID: " . $app_id . PHP_EOL;
        echo "End connection..." . PHP_EOL;
        $this->connections->detach($conn);
        $count = $this->connections->count();
        echo "Count: " . $count . PHP_EOL;
        echo "----------------------" . PHP_EOL;

        return;
      }

      echo "----------------------" . PHP_EOL;
      echo $response['message'] . PHP_EOL;
      echo "----------------------" . PHP_EOL;

      $conn->send((string)json_encode(array(
        "error" => true,
        "message" => $response['message']
      )));
      return;
    }

    $data = array(
      "id" => $response["data"][0]->id,
      "user" => $response["data"][0]->name,
      "auth" => $auth,
      "app_id" => $app_id
    );
    $this->connections->offsetSet($conn, $data);
    echo "----------------------" . PHP_EOL;
    echo "New connection for " . $data['user'] . PHP_EOL;
    echo "----------------------" . PHP_EOL;
    return;
  }

  function getDeviceConnection($imei, $conn)
  {
    try {
      $daoDevice = new DAODevice();
      $response = $daoDevice->SelectDeviceByImei($imei);

      if ($response['error']) {
        if ($response['message'] == "No data") {
          $conn->send((string)json_encode(array(
            "error" => true,
            "message" => "This device is not exist"
          )));

          echo "End connection..." . PHP_EOL;
          $this->connections->detach($conn);
          $count = $this->connections->count();
          echo "Count: " . $count . PHP_EOL;
          echo "----------------------" . PHP_EOL;

          return;
        }
        echo "----------------------" . PHP_EOL;
        echo $response['message'] . PHP_EOL;
        echo "----------------------" . PHP_EOL;

        $conn->send((string)json_encode(array(
          "error" => true,
          "message" => $response['message']
        )));
        return;
      }

      $data = array(
        "id" => (int)$response["data"][0]->id,
        "description" => $response["data"][0]->description,
        "shop_id" => (int) $response["data"][0]->shop_id,
        "dept_id" => (int) $response["data"][0]->dept_id,
        "imei" => $response["data"][0]->imei
      );

      $this->connections->offsetSet($conn, $data);

      echo "----------------------" . PHP_EOL;
      echo "New connection for " . $data['description'] . PHP_EOL;
      echo "IMEI " . $data['imei'] . PHP_EOL;
      echo "----------------------" . PHP_EOL;

      $conn->send(json_encode($data));
      return;
    } catch (Exception $e) {
      echo "----------------------" . PHP_EOL;
      echo "Error (SetConnection): " . $e->getMessage() . PHP_EOL;
      echo "----------------------" . PHP_EOL;
    }
  }

  function getDevicesOnline($conn, $jsonBody)
  {
    if (!isset($jsonBody['auth']) || !isset($jsonBody['app_id'])) {
      echo "----------------------" . PHP_EOL;
      echo "(auth,app_id) is broken" . PHP_EOL;
      echo "End connection..." . PHP_EOL;
      $this->connections->detach($conn);
      $count = $this->connections->count();
      echo "Count: " . $count . PHP_EOL;
      echo "----------------------" . PHP_EOL;
      $conn->send((string)json_encode(array("error" => true, "message" => "(auth, app_id) is broken")));
      return;
    }

    $daoUser = new DAOUser();
    $auth = $jsonBody["auth"];
    $app_id = $jsonBody["app_id"];
    $response = $daoUser->SelectSession($auth, $app_id);

    if ($response['error']) {
      if ($response['message'] == "No data") {
        $conn->send((string)json_encode(array(
          "error" => true,
          "message" => "This key is unauthorized SetConnection"
        )));

        echo "----------------------" . PHP_EOL;
        echo "This key is unauthorized: " . $auth . PHP_EOL;
        echo "For Application ID: " . $app_id . PHP_EOL;
        echo "End connection..." . PHP_EOL;
        $this->connections->detach($conn);
        $count = $this->connections->count();
        echo "Count: " . $count . PHP_EOL;
        echo "----------------------" . PHP_EOL;

        return;
      }

      echo "----------------------" . PHP_EOL;
      echo $response['message'] . PHP_EOL;
      echo "----------------------" . PHP_EOL;

      $conn->send((string)json_encode(array(
        "error" => true,
        "message" => $response['message']
      )));
      return;
    }

    try {
      $devicesList = array();

      foreach ($this->connections as $connection) {
        if (isset($this->connections[$connection]["imei"]) && $this->connections[$connection]["imei"] != null) {
          array_push($devicesList, $this->connections[$connection]);
        }
      }
      $conn->send(json_encode(array("error" => false, "devices" => $devicesList)));
    } catch (Exception $e) {
      echo "----------------------" . PHP_EOL;
      echo "Error (getDevicesOnline): " . $e->getMessage() . PHP_EOL;
      echo "----------------------" . PHP_EOL;
    }
  }
}
