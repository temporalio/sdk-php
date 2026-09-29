<?php

declare(strict_types=1);

use Google\Protobuf\Internal\Message;
use Temporal\Api\Workflowservice\V1\PollActivityTaskQueueResponse;
use Temporal\Api\Workflowservice\V1\RespondActivityTaskCompletedRequest;
use Temporal\Api\Workflowservice\V1\RespondActivityTaskCompletedResponse;
use Temporal\Api\Workflowservice\V1\RespondActivityTaskFailedResponse;
use Thesis\Grpc\Client;
use Thesis\Grpc\Client\Builder;
use Thesis\Grpc\Client\Invoke;
use Thesis\Grpc\Encoding\Encoder;
use Thesis\Grpc\Metadata;
use Thesis\Grpc\Metadata\Timeout;
use Thesis\Grpc\RpcType;

use function Amp\async;

require __DIR__ . '/tasks.php';

const SERVICE = '/temporal.api.workflowservice.v1.WorkflowService/';

final class GoogleProtobufEncoder implements Encoder
{
    public function name(): string
    {
        return 'proto';
    }

    public function encode(object $request): string
    {
        \assert($request instanceof Message);
        return $request->serializeToString();
    }

    public function decode(string $buffer, string $classType): object
    {
        $message = new $classType();
        $message->mergeFromString($buffer);
        return $message;
    }
}

function call(Client $client, string $method, Message $request, string $output): object
{
    return $client->invoke(
        $request,
        new Invoke(SERVICE . $method, $output, RpcType::Unary),
        (new Metadata())->withKey(Timeout::seconds(RPC_TIMEOUT_SECONDS)),
    );
}

function respond(Client $client, PollActivityTaskQueueResponse $task): void
{
    $response = runActivity($task);
    if ($response instanceof RespondActivityTaskCompletedRequest) {
        call($client, 'RespondActivityTaskCompleted', $response, RespondActivityTaskCompletedResponse::class);
        return;
    }
    call($client, 'RespondActivityTaskFailed', $response, RespondActivityTaskFailedResponse::class);
}

function pollLoop(Client $client): void
{
    $poll = pollRequest();
    while (true) {
        try {
            $task = call($client, 'PollActivityTaskQueue', $poll, PollActivityTaskQueueResponse::class);
        } catch (\Throwable $e) {
            \fwrite(\STDERR, "poll failed: {$e->getMessage()}\n");
            \Amp\delay(0.1);
            continue;
        }
        if ($task->getTaskToken() !== '') {
            async(respond(...), $client, $task)->catch(
                static fn(\Throwable $e) => \fwrite(\STDERR, "respond failed: {$e->getMessage()}\n"),
            );
        }
    }
}

$client = (new Builder())
    ->withHost(temporalAddress())
    ->withEncoding(new GoogleProtobufEncoder())
    ->build();

$pollers = [];
for ($i = 0, $n = (int) (\getenv('POLLERS') ?: 8); $i < $n; $i++) {
    $pollers[] = async(pollLoop(...), $client);
}
\Amp\Future\await($pollers);
