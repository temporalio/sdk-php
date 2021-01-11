<?php

use Temporal\Client;
use Temporal\Tests\Workflow\SimpleWorkflow;

require 'vendor/autoload.php';

$client = new Client\Client(
    Client\GRPC\ServiceClient::createInsecure('localhost:7233')
);

$w = $client->newWorkflowStub(
    \Temporal\Tests\Workflow\SimpleSignalledWorkflowWithSleep::class,
    Client\WorkflowOptions::new()
);

$run = $w->handler();
sleep(1);

$w->add(-1);

dump($run->getResult());

$w = $client->newWorkflowStub(
    \Temporal\Tests\Workflow\SimpleWorkflow::class,
    Client\WorkflowOptions::new()
);

$run = $w->handler('hello!!');
dump($run->getResult());

//$w = $client->newWorkflowStub(
//    \Temporal\Tests\Workflow\QueryWorkflow::class,
//    Client\WorkflowOptions::new()
//        ->withWorkflowId('test')
//);
//
//$run = $w->handler();
//usleep(50000);
//
//dump($w->get());
//$w->add(-1);
//dump($w->get());
//
//dump($run->getResult());
