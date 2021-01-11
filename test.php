<?php

use Temporal\Client;
use Temporal\Tests\Workflow\SimpleWorkflow;

require 'vendor/autoload.php';

$client = new Client\Client(
    Client\GRPC\ServiceClient::createInsecure('localhost:7233')
);

$w = $client->newWorkflowStub(SimpleWorkflow::class, Client\WorkflowOptions::new());

// todo: how to deal with it?
dump($w->handler('hello world')->getResult());
