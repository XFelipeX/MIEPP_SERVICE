<?php

namespace App\DAO\Device;

use Conn\Connection\Connection;

// require_once('../../../Connection.php');

use Exception;

class DAODevice
{
  function SelectDeviceByImei($imei): array
  {
    try {
      $conn = new Connection();
      $response = $conn->Select("SELECT * FROM device WHERE imei like ?;", [$imei]);
      return $response;
    } catch (Exception $e) {
      return $e;
    }
  }
}
