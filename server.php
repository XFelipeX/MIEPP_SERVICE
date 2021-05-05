<?php

use Ratchet\Server\IoServer;
use Ratchet\Http\HttpServer;
use Ratchet\WebSocket\WsServer;

require_once('./src/Socket/Component.php');

require 'vendor/autoload.php';

$server = IoServer::factory(
  new HttpServer(
    new WsServer(
      new Component()
    )
  ),
  9090
);

$server->run();
