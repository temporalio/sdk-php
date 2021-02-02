<?php

declare(strict_types=1);

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Temporal\Workflow;

use Temporal\Exception\CompensationException;
use Temporal\Promise;
use Temporal\Workflow;

final class Saga
{
    private bool $parallelCompensation = false;
    private bool $continueWithError = false;
    private array $compensate = [];

    /**
     * This decides if the compensation operations are run in parallel. If parallelCompensation is
     * false, then the compensation operations will be run the reverse order as they are added.
     *
     * @param bool $parallelCompensation default is false.
     * @return self
     */
    public function setParallelCompensation(bool $parallelCompensation): self
    {
        $this->parallelCompensation = $parallelCompensation;
        return $this;
    }

    /**
     * continueWithError gives user the option to bail out of compensation operations if exception
     * is thrown while running them. This is useful only when parallelCompensation is false. If
     * parallel compensation is set to true, then all the compensation operations will be fired no
     * matter what and caller will receive exceptions back if there's any.
     *
     * @param bool $continueWithError whether to proceed with the next compensation operation if the
     *     previous throws exception. This only applies to sequential compensation. Default is
     *     false.
     * @return self
     */
    public function setContinueWithError(bool $continueWithError): self
    {
        $this->continueWithError = $continueWithError;
        return $this;
    }

    /**
     * @param callable $handler
     */
    public function addCompensation(callable $handler): void
    {
        $this->compensate[] = $handler;
    }

    /**
     * Run compensation strategy. Make sure to yield on tis method.
     */
    public function compensate(): CancellationScopeInterface
    {
        return Workflow::asyncDetached(
            function () {
                if ($this->parallelCompensation) {
                    $scopes = [];
                    foreach ($this->compensate as $handler) {
                        $scopes[] = Workflow::asyncDetached($handler);
                    }

                    yield Promise::all($scopes);
                    return;
                }

                $sagaException = null;

                for ($i = count($this->compensate) - 1; $i >= 0; $i--) {
                    $handler = $this->compensate[$i];
                    try {
                        yield Workflow::asyncDetached($handler);
                    } catch (\Throwable $e) {
                        if ($sagaException === null) {
                            $sagaException = new CompensationException($e->getMessage(), $e->getCode(), $e);
                        }

                        if (!$this->continueWithError) {
                            throw $e;
                        }

                        $sagaException->addSuppressed($e);
                    }
                }

                if ($sagaException !== null) {
                    throw $sagaException;
                }
            }
        );
    }
}
