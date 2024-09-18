<?php

declare(strict_types=1);

require_once 'vendor/autoload.php';

return \Spiral\CodeStyle\Builder::create()
    ->include(__DIR__ . '/src')
    ->include(__DIR__ . '/testing/src')
    ->include(__FILE__)
    ->exclude(__DIR__ . '/src/Client/GRPC/ServiceClientInterface.php')
    ->allowRisky(false)
    ->build();
