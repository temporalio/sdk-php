<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Client\Workflow;

use JetBrains\PhpStorm\Pure;
use React\Promise\PromiseInterface;
use Temporal\Client\Internal\Declaration\Prototype\ActivityPrototype;
use Temporal\Client\Internal\Declaration\Prototype\Collection;
use Temporal\Client\Internal\Transport\CapturedClientInterface;
use Temporal\Client\Internal\Workflow\InputAwareTrait;
use Temporal\Client\Internal\Workflow\Process\CancellationScope;
use Temporal\Client\Internal\Workflow\Requests;
use Temporal\Client\Internal\Workflow\RequestsAwareTrait;
use Temporal\Client\Worker\Environment\EnvironmentAwareTrait;
use Temporal\Client\Worker\Environment\EnvironmentInterface;
use Temporal\Client\Worker\LoopInterface;
use Temporal\Client\Workflow\Context\InputInterface;

class WorkflowContext implements WorkflowContextInterface
{
    use InputAwareTrait;
    use RequestsAwareTrait;
    use EnvironmentAwareTrait;

    /**
     * @var Collection<ActivityPrototype>
     */
    private Collection $activities;

    /**
     * @var LoopInterface
     */
    private LoopInterface $loop;

    /**
     * @param LoopInterface $loop
     * @param EnvironmentInterface $env
     * @param InputInterface $input
     * @param Requests $requests
     */
    public function __construct(
        LoopInterface $loop,
        EnvironmentInterface $env,
        InputInterface $input,
        Requests $requests
    ) {
        $this->env = $env;
        $this->input = $input;
        $this->requests = $requests;
        $this->loop = $loop;
    }

    /**
     * @return CapturedClientInterface
     */
    #[Pure]
    public function getClient(): CapturedClientInterface
    {
        return $this->requests->getClient();
    }

    /**
     * @param callable $handler
     * @return PromiseInterface
     */
    public function newCancellationScope(callable $handler): CancellationScope
    {
        return new CancellationScope($this->withNewScope(), $this->loop, \Closure::fromCallable($handler));
    }

    /**
     * @return $this
     */
    #[Pure]
    public function withNewScope(): self
    {
        $self = clone $this;
        $self->requests = $this->requests->withNewScope();

        return $self;
    }
}
