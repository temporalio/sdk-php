<?php

/**
 * Spiral Framework.
 *
 * @license   MIT
 * @author    Anton Titov (Wolfy-J)
 */

declare(strict_types=1);

namespace Temporal\Client\Future;

use React\Promise\CancellablePromiseInterface;
use React\Promise\PromiseInterface;
use Temporal\Client\Worker\Loop;

class Future implements FutureInterface
{
    private $resolved = false;
    private $value;

    /** @var callable|null */
    private $onComplete;

    /** @var CancellablePromiseInterface */
    private $promise;

    public function __construct(CancellablePromiseInterface $promise)
    {
        $this->promise = $promise->then(function ($result) {
            $this->resolved = true;
            $this->value = $result;

            Loop::onTick(function () {
                if ($this->onComplete !== null) {
                    ($this->onComplete)($this->value);
                }
            }, Loop::ON_CALLBACK);
        });
    }

    public function onComplete(callable $onComplete): FutureInterface
    {
        $this->onComplete = $onComplete;
        return $this;
    }

    public function isComplete(): bool
    {
        return $this->resolved;
    }

    public function cancel()
    {
        $this->promise->cancel();
    }

    public function promise(): PromiseInterface
    {
        return $this->promise;
    }
}
