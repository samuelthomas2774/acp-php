<?php

require_once __DIR__ . '/vendor/autoload.php';

use AirPort\Client;

$client = new Client('192.168.2.251', 5009, 'testing');

$client->connect();

var_dump($client);

$props = $client->getProperties(['dbug']);

var_dump($props);
var_dump($props[0]->format());
