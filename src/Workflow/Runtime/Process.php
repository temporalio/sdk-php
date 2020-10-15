<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Client\Workflow\Runtime;

/**
 * @internal Process is an internal library class, please do not use it in your code.
 * @psalm-internal Temporal\Client\Workflow
 */
final class Process
{
    /**
     * @var WorkflowContextInterface
     */
    private WorkflowContextInterface $context;

    /**
     * @param WorkflowContextInterface $context
     */
    public function __construct(WorkflowContextInterface $context)
    {
        $this->context = $context;
    }

    public function run(callable $handler)
    {
        // TODO
    }
}
