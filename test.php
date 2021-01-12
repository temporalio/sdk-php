<?php

use Temporal\Client;
use Temporal\Tests\Workflow\SimpleWorkflow;

require 'vendor/autoload.php';

$client = new Client\Client(
    Client\GRPC\ServiceClient::createInsecure('localhost:7233')
);

//$w = $client->newWorkflowStub(
//    \Temporal\Tests\Workflow\SimpleSignalledWorkflowWithSleep::class,
//    Client\WorkflowOptions::new()
//);
//
//$run = $w->handler();
//sleep(1);
//
//$w->add(-1);
//
//dump($run->getResult());
//
//$w = $client->newWorkflowStub(
//    \Temporal\Tests\Workflow\SimpleWorkflow::class,
//    Client\WorkflowOptions::new()
//);
//
//$run = $w->handler('hello!!');
//dump($run->getResult());

$w = $client->newWorkflowStub(
    \Temporal\Tests\Workflow\QueryWorkflow::class,
    Client\WorkflowOptions::new()
        ->withWorkflowId('test7')
);

// todo: return untyped stub (via interface)
// $client->start($w, $arg, ...);

/** @var \Temporal\Workflow\WorkflowRun $run */
$run = $w->handler();

// $client->execute($w, $arg, ...);

//dump($run->getResult());

//try {
//} catch (\Temporal\Exception\ServiceClientException $e) {
// https://dev.to/khepin/grpc-advanced-error-handling-from-go-to-php-1omc
// try {
//  dump($e->getStatus());
//  $a = new \Google\Rpc\Status();
// $a->mergeFromString($e->getBinaryDetails()[0]);
// dumP($a->getDetails()->offsetGet(0)->getTypeUrl());
//  dump($a->is(\Temporal\Api\Errordetails\V1\WorkflowExecutionAlreadyStartedFailure::class));
//  } catch (Throwable $e) {
//    echo $e;
//   dump($e);
//}
//}
//usleep(50000);
//
//dump($w->get());
//$w->add(-1);
//dump($w->get());
//
