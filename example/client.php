<?php

use App\Activity\ExampleActivity;
use App\SocketStream;
use App\Workflow\PizzaDelivery;
use React\EventLoop\Factory;
use Spiral\Core\Container;
use Spiral\Goridge\AsyncReceiver;
use Spiral\Goridge\Protocol\GoridgeV2;
use Spiral\Goridge\Responder;
use Temporal\Client\Transport\JsonRpcTransport;
use Temporal\Client\Worker;

require __DIR__ . '/../vendor/autoload.php';

/**
 * Create socket connection.
 */
$socket = SocketStream::create('tcp://127.0.0.1:8080');

/**
 * Create Container.
 */
$container = new Container();

/**
 * Use Goridge v2 low-level transport protocol.
 */
$protocol = new GoridgeV2();

/**
 * Create new event loop.
 */
$loop = Factory::create();

/**
 * Create facade for duplex socket stream.
 */
$transport = new JsonRpcTransport(
    new AsyncReceiver($socket, $protocol, $loop),
    new Responder($socket, $protocol)
);

// =============================================================================
//  TEMPORAL
// =============================================================================

$worker = new Worker($container, $transport, $loop);

$worker->addWorkflow(PizzaDelivery::toWorkflow());
$worker->addActivity(ExampleActivity::toActivity());

$worker->run();
