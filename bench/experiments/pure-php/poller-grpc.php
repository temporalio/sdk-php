<?php

declare(strict_types=1);

use Grpc\ChannelCredentials;
use Temporal\Api\Workflowservice\V1\RespondActivityTaskCompletedRequest;
use Temporal\Api\Workflowservice\V1\WorkflowServiceClient;

require __DIR__ . '/tasks.php';

$client = new WorkflowServiceClient(temporalAddress(), ['credentials' => ChannelCredentials::createInsecure()]);
$poll = pollRequest();
$options = ['timeout' => RPC_TIMEOUT_SECONDS * 1_000_000];

while (true) {
    [$task, $status] = $client->PollActivityTaskQueue($poll, [], $options)->wait();
    if ($status->code !== \Grpc\STATUS_OK) {
        \fwrite(\STDERR, "poll failed: {$status->details}\n");
        \usleep(100_000);
        continue;
    }
    if ($task->getTaskToken() === '') {
        continue;
    }

    $response = runActivity($task);
    [, $status] = $response instanceof RespondActivityTaskCompletedRequest
        ? $client->RespondActivityTaskCompleted($response, [], $options)->wait()
        : $client->RespondActivityTaskFailed($response, [], $options)->wait();
    if ($status->code !== \Grpc\STATUS_OK) {
        \fwrite(\STDERR, "respond failed: {$status->details}\n");
    }
}
