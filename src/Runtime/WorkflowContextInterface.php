<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Client\Runtime;

use React\Promise\PromiseInterface;

interface WorkflowContextInterface
{
    /**
     * @return string
     */
    public function getId(): string;

    /**
     * @return string
     */
    public function getRunId(): string;

    /**
     * @return string
     */
    public function getWorkerId(): string;

    /**
     * @return mixed
     */
    public function getPayload();

    /**
     * @param mixed $result
     * @return PromiseInterface
     */
    public function complete($result = null): PromiseInterface;

    /**
     * @param string $name
     * @param array $arguments
     * @param ActivityOptions|null $opt
     * @return PromiseInterface
     */
    public function executeActivity(string $name, array $arguments = [], ActivityOptions $opt = null): PromiseInterface;
}
