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
use Temporal\Client\Transport\Request\InputRequestInterface;

interface WorkflowContextInterface
{
    /**
     * @return string|int
     */
    public function getId();

    /**
     * @return string|int
     */
    public function getRunId();

    /**
     * @return string
     */
    public function getWorkerId(): string;

    /**
     * @return InputRequestInterface
     */
    public function getRequest(): InputRequestInterface;

    /**
     * @return mixed
     */
    public function getPayload();

    /**
     * @return void
     */
    public function complete(): void;

    /**
     * @param string $name
     * @param array $arguments
     * @return PromiseInterface
     */
    public function executeActivity(string $name, array $arguments = []): PromiseInterface;
}
