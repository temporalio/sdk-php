<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Client\Internal\Workflow;

use React\Promise\PromiseInterface;
use Temporal\Client\Worker\Command\RequestInterface;
use Temporal\Client\Workflow\Context\RequestsInterface;

/**
 * @mixin RequestsInterface
 */
trait RequestsAwareTrait
{
    /**
     * @var Requests
     */
    protected Requests $requests;

    /**
     * {@inheritDoc}
     */
    public function request(RequestInterface $request): PromiseInterface
    {
        return $this->requests->request($request);
    }

    /**
     * {@inheritDoc}
     */
    public function getVersion(string $changeId, int $minSupported, int $maxSupported): PromiseInterface
    {
        return $this->requests->getVersion($changeId, $minSupported, $maxSupported);
    }

    /**
     * {@inheritDoc}
     */
    public function sideEffect(callable $context): PromiseInterface
    {
        return $this->requests->sideEffect($context);
    }

    /**
     * {@inheritDoc}
     */
    public function complete($result = null): PromiseInterface
    {
        return $this->requests->complete($result);
    }

    /**
     * {@inheritDoc}
     */
    public function executeActivity(string $name, array $arguments = [], $options = null): PromiseInterface
    {
        return $this->requests->executeActivity($name, $arguments, $options);
    }

    /**
     * {@inheritDoc}
     */
    public function newActivityStub(string $name, $options = null): object
    {
        return $this->requests->newActivityStub($name, $options);
    }

    /**
     * {@inheritDoc}
     */
    public function timer($interval): PromiseInterface
    {
        return $this->requests->timer($interval);
    }
}
