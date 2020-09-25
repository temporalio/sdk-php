<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Client\Runtime\Route;

use React\Promise\Deferred;
use Temporal\Client\Declaration\CollectionInterface;
use Temporal\Client\Declaration\WorkflowInterface;
use Temporal\Client\Protocol\ClientInterface;
use Temporal\Client\Runtime\Queue\EntryInterface;
use Temporal\Client\Runtime\Queue\RequestQueue;
use Temporal\Client\Runtime\Queue\RequestQueueInterface;
use Temporal\Client\Runtime\WorkflowContext;

class StartWorkflow extends Route
{
    /**
     * @psalm-var CollectionInterface<WorkflowInterface>
     *
     * @var CollectionInterface
     */
    private CollectionInterface $workflows;

    /**
     * @var ClientInterface
     */
    private ClientInterface $client;

    /**
     * @param CollectionInterface $workflows
     * @param ClientInterface $client
     */
    public function __construct(CollectionInterface $workflows, ClientInterface $client)
    {
        $this->client = $client;
        $this->workflows = $workflows;
    }

    /**
     * @param array $params
     * @return string|null
     */
    private function getErrorMessage(array $params): ?string
    {
        if (! isset($params['name']) || ! \is_string($params['name'])) {
            return 'Required field "name" is missing or contains an invalid type';
        }

        if (! isset($params['wid']) || ! \is_string($params['wid'])) {
            return 'Required field "wid" is missing or contains an invalid type';
        }

        if (! isset($params['rid']) || ! \is_string($params['rid'])) {
            return 'Required field "wid" is missing or contains an invalid type';
        }

        if (isset($params['taskQueue']) && ! \is_string($params['taskQueue'])) {
            return 'Required field "taskQueue" contains an invalid type';
        }

        return null;
    }

    /**
     * @psalm-import-type WorkflowContextParams from WorkflowContext
     * @psalm-param WorkflowContextParams $params
     *
     * @param array $params
     * @param Deferred $resolver
     */
    public function handle(array $params, Deferred $resolver): void
    {
        if ($error = $this->getErrorMessage($params)) {
            throw new \InvalidArgumentException($error);
        }

        /** @var WorkflowInterface $workflow */
        $workflow = $this->workflows->find($params['name']);

        if ($workflow === null) {
            $error = \sprintf('Workflow "%s" has not been registered', $params['name']);
            throw new \InvalidArgumentException($error);
        }

        $queue = new RequestQueue();
        $context = new WorkflowContext($params, $queue);

        // TODO auto resolve parameters instead of "$context" passing.
        $result = ($workflow->getHandler())($context);

        if ($result instanceof \Generator) {
            $this->processGenerator($result, $queue, $resolver);
        } else {
            $this->processResult($queue, $resolver);
        }
    }

    /**
     * @param RequestQueueInterface $queue
     * @param Deferred $deferred
     */
    private function processResult(RequestQueueInterface $queue, Deferred $deferred): void
    {
        /** @var EntryInterface $entry */
        foreach ($queue as $entry) {
            // TODO In the case that the request is a "CompleteWorkflow", then $deferred should be resolved
            $this->client->request($entry->request)
                ->then(function ($response) use ($entry) {
                    $entry->resolver->resolve($response);
                });
        }
    }

    /**
     * @param \Generator $generator
     * @param RequestQueueInterface $queue
     * @param Deferred $resolver
     */
    private function processGenerator(\Generator $generator, RequestQueueInterface $queue, Deferred $resolver): void
    {
        if ($generator->valid()) {
            // TODO assert that it is an instance of PromiseInterface
            $promise = $generator->current();

            // TODO Check that the $promise is part of the queue
            // TODO In the case that the request is a "CompleteWorkflow", then $deferred should be resolved
            $entry = $queue->pull($promise);

            $this->client->request($entry->request)
                ->then(function ($response) use ($entry, $generator, $queue, $resolver) {
                    $entry->resolver->resolve($response);

                    $generator->send($response);

                    $this->processGenerator($generator, $queue, $resolver);
                });

            return;
        }

        $resolver->resolve($generator->getReturn());
    }
}
