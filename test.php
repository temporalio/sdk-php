<?php

use Temporal\Client;

require 'vendor/autoload.php';

$client = new Client\Client(
    Client\GRPC\ServiceClient::createInsecure('localhost:7233')
);

$w = $client->newUntypedWorkflowStub(
    'SimpleWorkflow',
    Client\WorkflowOptions::new()
);

// todo: get result
dump($w->start('hello world'));

dump($w->getResult());
