<?php

namespace app;

use Ratchet\MessageComponentInterface;
use Ratchet\ConnectionInterface;

class Game implements MessageComponentInterface
{

  protected $clients;

  public function __construct() {
    $this->clients = new \SplObjectStorage;
  }

  public function onOpen(ConnectionInterface $conn)
  {
    // Store the new connection to send messages to later
    $this->clients->attach($conn);

    echo "New connection! ({$conn->resourceId})\n";
  }

  public function onMessage(ConnectionInterface $from, $msg)
  {

    $numRecv = count($this->clients) - 1;
    echo sprintf('Connection %d sending message "%s" to %d other connection%s' . "\n"
    , $from->resourceId, $msg, $numRecv, $numRecv == 1 ? '' : 's');

  }

  public function onClose(ConnectionInterface $conn)
  {

  }

  public function onError(ConnectionInterface $conn, \Exception $e)
  {

  }
}