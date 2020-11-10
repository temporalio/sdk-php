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
//sleep(2);

//$result = [
//    "id"    => "425f0f46-3bb6-4b8d-8ee0-1b713c4710f5",
//    "runId" => "8719cd99-80e3-4910-9ad9-4fce8fd8c26d"
//];

////
//$rpc->call('temporal.SignalWorkflow', [
//    'wid'         => $result['id'],
//    'rid'         => $result['runId'],
//    'signal_name' => 'App\\Workflow\\PizzaDelivery::add',
//    'args'        => 10,
//]);
//
//dump(($rpc->call('temporal.QueryWorkflow', [
//    'wid'        => $result['id'],
//    'rid'        => $result['runId'],
//    'query_type' => 'App\\Workflow\\PizzaDelivery::get',
//    'args'       => [],
//])));
