<?php

namespace app;

use Yii;
use app\models\Login;
use Ratchet\MessageComponentInterface;
use Ratchet\ConnectionInterface;
use yii\helpers\Json;
use yii\helpers\VarDumper;
use yii\web\Request;

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

    echo VarDumper::dump($_SERVER);
  }

  public function onMessage(ConnectionInterface $from, $msg)
  {
    $numRecv = count($this->clients) - 1;
    /*echo sprintf('Connection %d sending message "%s" to %d other connection%s' . "\n"
    , $from->resourceId, $msg, $numRecv, $numRecv == 1 ? '' : 's');
  */


   /* $login = Json::decode($msg);

    $model = new Login();

    $model->username = $login['username'];
    $model->password = Yii::$app->getSecurity()->generatePasswordHash($login['password']);
    $model->online = 1;
    $model->uuid = $login['uuid'];*/

    //$model->save();

  }

  public function onClose(ConnectionInterface $conn)
  {

  }

  public function onError(ConnectionInterface $conn, \Exception $e)
  {

  }
}