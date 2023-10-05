<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Workflow;

use Temporal\Internal\Repository\Identifiable;

/**
 * @template T
 * @extends CancellationScopeInterface<T>
 */
interface ProcessInterface extends CancellationScopeInterface, Identifiable
{
    /**
     * @return WorkflowContextInterface
     */
    public function getContext(): WorkflowContextInterface;
}
