<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Client;

use Temporal\DataConverter\EncodedValues;
use Temporal\Workflow\WorkflowExecution;
use Temporal\Internal\Support\DateInterval;

/**
 * @psalm-import-type DateIntervalValue from DateInterval
 */
interface WorkflowStubInterface
{
    /**
     * @return string
     */
    public function getWorkflowType(): string;

    /**
     * @return WorkflowOptions
     */
    public function getOptions(): WorkflowOptions;

    /**
     * @return WorkflowExecution
     */
    public function getExecution(): WorkflowExecution;

    /**
     * @param string $name
     * @param array $args
     */
    public function signal(string $name, array $args = []): void;

    /**
     * @param string $name
     * @param array $args
     * @return EncodedValues|null
     */
    public function query(string $name, array $args = []): ?EncodedValues;

    /**
     * @param mixed ...$args
     * @return WorkflowExecution|null
     */
    public function start(...$args): ?WorkflowExecution;

    /**
     * @param string $signal
     * @param array $signalArgs
     * @param array $startArgs
     * @return WorkflowExecution
     */
    public function signalWithStart(string $signal, array $signalArgs = [], array $startArgs = []): WorkflowExecution;

    /**
     * @param DateIntervalValue|null $timeout
     * @param mixed $returnType
     * @return mixed
     * @see DateInterval
     */
    public function getResult($timeout = null, $returnType = null);

    /**
     * @return void
     */
    public function cancel(): void;

    /**
     * @param string $reason
     * @param array $details
     * @return void
     */
    public function terminate(string $reason, array $details = []): void;
}
