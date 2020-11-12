<?php

declare(strict_types=1);

use Spiral\Goridge\RPC;
use Spiral\Goridge\SocketRelay;

require __DIR__ . '/../vendor/autoload.php';

$rpc = new RPC(new SocketRelay('localhost', 6001));

dump($argv[1]);

dump($rpc->call('temporal.CompleteActivity', [
    'taskToken' => $argv[1],
    'result'    => 'Nice!'
]));
