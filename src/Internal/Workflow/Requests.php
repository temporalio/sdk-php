<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Client\Internal\Workflow;

use JetBrains\PhpStorm\Pure;
use React\Promise\PromiseInterface;
use Temporal\Client\Activity\ActivityOptions;
use Temporal\Client\Internal\Declaration\Prototype\ActivityPrototype;
use Temporal\Client\Internal\Declaration\Prototype\Collection;
use Temporal\Client\Internal\Marshaller\MarshallerInterface;
use Temporal\Client\Internal\Repository\RepositoryInterface;
use Temporal\Client\Internal\Support\DateInterval;
use Temporal\Client\Internal\Transport\CapturedClient;
use Temporal\Client\Internal\Transport\CapturedClientInterface;
use Temporal\Client\Internal\Transport\ClientInterface;
use Temporal\Client\Internal\Transport\Request\CompleteWorkflow;
use Temporal\Client\Internal\Transport\Request\ExecuteActivity;
use Temporal\Client\Internal\Transport\Request\GetVersion;
use Temporal\Client\Internal\Transport\Request\NewTimer;
use Temporal\Client\Internal\Transport\Request\SideEffect;
use Temporal\Client\Worker\Command\RequestInterface;
use Temporal\Client\Worker\Environment\EnvironmentInterface;
use Temporal\Client\Workflow\Context\RequestsInterface;

use function React\Promise\reject;

/**
 * @internal Requests is an internal library class, please do not use it in your code.
 * @psalm-internal Temporal\Client
 */
final class Requests implements RequestsInterface
{
    /**
     * @var CapturedClientInterface
     */
    private CapturedClientInterface $client;

    /**
     * @var MarshallerInterface
     */
    private MarshallerInterface $marshaller;

    /**
     * @var EnvironmentInterface
     */
    private EnvironmentInterface $env;

    /**
     * @var RepositoryInterface<ActivityPrototype>
     */
    private RepositoryInterface $activities;

    /**
     * @param MarshallerInterface $marshaller
     * @param EnvironmentInterface $env
     * @param ClientInterface $client
     * @param RepositoryInterface<ActivityPrototype>
     */
    public function __construct(
        MarshallerInterface $marshaller,
        EnvironmentInterface $env,
        ClientInterface $client,
        RepositoryInterface $activities
    ) {
        $this->marshaller = $marshaller;
        $this->env = $env;
        $this->client = new CapturedClient($client);
        $this->activities = $activities;
    }

    /**
     * @return $this
     */
    #[Pure]
    public function withNewScope(): self
    {
        $self = clone $this;
        $self->client = new CapturedClient($this->client);

        return $self;
    }

    /**
     * @return CapturedClientInterface
     */
    public function getClient(): CapturedClientInterface
    {
        return $this->client;
    }

    /**
     * {@inheritDoc}
     */
    public function getVersion(string $changeId, int $minSupported, int $maxSupported): PromiseInterface
    {
        $request = new GetVersion($changeId, $minSupported, $maxSupported);

        return $this->request($request);
    }

    /**
     * {@inheritDoc}
     */
    public function request(RequestInterface $request): PromiseInterface
    {
        return $this->client->request($request);
    }

    /**
     * {@inheritDoc}
     */
    public function sideEffect(callable $context): PromiseInterface
    {
        $isReplaying = $this->env->isReplaying();

        try {
            $value = $isReplaying ? null : $context();
        } catch (\Throwable $e) {
            return reject($e);
        }

        return $this->request(new SideEffect($value));
    }

    /**
     * {@inheritDoc}
     */
    public function complete($result = null): PromiseInterface
    {
        $map = static fn(RequestInterface $request): int => $request->getId();

        $request = new CompleteWorkflow($result, \array_map($map, $this->client->fetchUnresolvedRequests()));

        $onFulfilled = function ($result) {
            // TODO Kill process

            return $result;
        };

        /** @psalm-suppress UnusedClosureParam */
        $onRejected = function (\Throwable $exception) {
            // TODO Kill process

            throw $exception;
        };

        return $this->request($request)
            ->then($onFulfilled, $onRejected)
            ;
    }

    /**
     * {@inheritDoc}
     */
    public function executeActivity(string $name, array $arguments = [], $options = null): PromiseInterface
    {
        $options = $this->marshaller->marshal(
            $this->options($options, ActivityOptions::class)
        );

        return $this->request(new ExecuteActivity($name, $arguments, $options));
    }

    /**
     * {@inheritDoc}
     */
    public function newActivityStub(string $name, $options = null): ActivityProxy
    {
        $options = $this->options($options, ActivityOptions::class);

        return new ActivityProxy($name, $options, $this, $this->activities);
    }

    /**
     * @psalm-template T of object
     *
     * @param T|array|null $options
     * @param class-string<T> $class
     * @return T
     */
    private function options($options, string $class): object
    {
        switch (true) {
            case $options === null:
                return new $class();

            case \is_array($options):
                return $this->marshaller->unmarshal($options, new $class());

            case $options instanceof $class:
                return $options;

            default:
                throw new \InvalidArgumentException('Invalid options argument');
        }
    }

    /**
     * {@inheritDoc}
     */
    public function timer($interval): PromiseInterface
    {
        $interval = DateInterval::parse($interval, DateInterval::FORMAT_SECONDS);

        return $this->request(new NewTimer($interval));
    }
}
