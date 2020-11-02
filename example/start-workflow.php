<?php

declare(strict_types=1);

use Spiral\Goridge\RPC;
use Spiral\Goridge\SocketRelay;

require __DIR__ . '/../vendor/autoload.php';

$rpc = new RPC(new SocketRelay('localhost', 6001));

for ($i = 0; $i < 1; $i++) {
    $result = $rpc->call('temporal.ExecuteWorkflow', [
        'name'    => 'PizzaDelivery',
        'input'   => ['hello ' . $i],
        'options' => [
            'taskQueue'                => 'default',
            'workflowExecutionTimeout' => '60s',
            'workflowRunTimeout'       => '60s',
            'workflowTaskTimeout'      => '60s',
        ],
    ]);
    var_dump($i);
}

var_dump($result);
sleep(2);

$rpc->call('temporal.SignalWorkflow', [
    'wid'         => $result['id'],
    'rid'         => $result['runId'],
    'signal_name' => 'App\\Workflow\\PizzaDelivery::retryNow',
    'args'        => 100,
]);

$rpc->call('temporal.QueryWorkflow', [
    'wid'        => $result['id'],
    'rid'        => $result['runId'],
    'query_type' => 'App\\Workflow\\PizzaDelivery::getStatus',
    'args'       => ["test", 123],
]);
