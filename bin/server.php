<?php

use Ratchet\Server\IoServer;
use Ratchet\Http\HttpServer;
use Ratchet\WebSocket\WsServer;
use app\Game;

require dirname(__DIR__) . '/vendor/autoload.php';

require(dirname(__DIR__) . '/vendor/yiisoft/yii2/Yii.php');

$yiiConfig = require(dirname(__DIR__) . '/config/yii.php');

new yii\web\Application($yiiConfig); // Do NOT call run() here

$server = IoServer::factory(
    new HttpServer(
        new WsServer(
            new Game()
        )
    ),
    8060
);

$server->run();