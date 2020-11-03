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
use React\Promise\Deferred;
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
    private $deferred;

    public function __construct(CancellablePromiseInterface $promise)
    {
        $this->deferred = new Deferred();
        $this->promise = $promise->then(function ($result) {
            $this->resolved = true;
            $this->value = $result;

            Loop::onTick(function () {
                if ($this->onComplete !== null) {
                    $value = ($this->onComplete)($this->value);
                } else {
                    $value = $this->value;
                }

                $this->deferred->resolve($value);
            }, Loop::ON_CALLBACK);
        });
    }

    public function onComplete(callable $onComplete): PromiseInterface
    {
        $this->onComplete = $onComplete;

        return $this->deferred->promise();
    }

    public function isComplete(): bool
    {
        return $this->resolved;
    }

    public function getValue()
    {
        return $this->value;
    }

    public function cancel()
    {
        $this->promise->cancel();
    }

    public function promise(): PromiseInterface
    {
        return $this->deferred->promise();
    }
}
