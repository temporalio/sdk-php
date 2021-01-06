<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Internal\Workflow\Process;

use JetBrains\PhpStorm\Pure;
use Temporal\Internal\Declaration\WorkflowInstanceInterface;
use Temporal\Internal\ServiceContainer;
use Temporal\Workflow\ProcessInterface;
use Temporal\Workflow\WorkflowContext;

class Process extends CoroutineScope implements ProcessInterface
{
    /**
     * Process constructor.
     * @param ServiceContainer $services
     * @param WorkflowContext $ctx
     */
    public function __construct(ServiceContainer $services, WorkflowContext $ctx)
    {
        parent::__construct($services, $ctx);

        // unlike other scopes Process will notify the server when complete instead of pushing the result
        // to parent scope (there are no parent scope)
        $this->promise()->then(
            function ($result) {
                $this->complete([$result]);
            },
            function (\Throwable $e) {
                $this->complete($e);
            }
        );
    }

    /**
     * @return mixed|string
     */
    public function getId()
    {
        return $this->context->getRunId();
    }

    /**
     * @return WorkflowInstanceInterface
     */
    #[Pure]
    public function getWorkflowInstance(): WorkflowInstanceInterface
    {
        return $this->getContext()->getWorkflowInstance();
    }

    /**
     * @param $result
     */
    protected function complete($result)
    {
        if ($this->context->isContinuedAsNew()) {
            return;
        }

        $this->context->complete($result);
    }
}
