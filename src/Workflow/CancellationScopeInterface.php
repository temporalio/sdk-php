<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Workflow;

use React\Promise\CancellablePromiseInterface;
use React\Promise\PromiseInterface;
use Temporal\Internal\Repository\Identifiable;

interface CancellationScopeInterface extends
    Identifiable,
    PromiseInterface,
    CancellablePromiseInterface
{
    /**
     * @return string
     */
    public function getId(): string;

    /**
     * @param callable $then
     * @return $this
     */
    public function onCancel(callable $then): self;
}
