<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Client\Internal\Workflow\Process;

use Temporal\Client\Internal\Workflow\Requests;
use Temporal\Client\Worker\Environment\EnvironmentInterface;
use Temporal\Client\Worker\LoopInterface;
use Temporal\Client\Workflow\Context\InputInterface;
use Temporal\Client\Workflow\WorkflowContext;

class Process extends Scope
{
    /**
     * @param LoopInterface $loop
     * @param EnvironmentInterface $env
     * @param InputInterface $input
     * @param Requests $requests
     * @param callable $handler
     */
    public function __construct(
        LoopInterface $loop,
        EnvironmentInterface $env,
        InputInterface $input,
        Requests $requests,
        callable $handler
    ) {
        $context = new WorkflowContext($loop, $env, $input, $requests);

        parent::__construct($context, $loop, $handler, $context->getArguments());
    }
}
