<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Client\Workflow;

use Temporal\Client\Workflow\Context\InputInterface;
use Temporal\Client\Workflow\Context\RequestsInterface;
use Temporal\Client\Worker\Environment\EnvironmentInterface;

interface ContextInterface extends
    EnvironmentInterface,
    RequestsInterface,
    InputInterface
{
    /**
     * @param callable $handler
     * @return CancellationScopeInterface
     */
    public function newCancellationScope(callable $handler): CancellationScopeInterface;
}
