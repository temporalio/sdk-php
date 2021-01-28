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
use Temporal\DataConverter\ValuesInterface;
use Temporal\Exception\DestructMemorizedInstanceException;
use Temporal\Exception\InvalidArgumentException;
use Temporal\Internal\Declaration\WorkflowInstanceInterface;
use Temporal\Internal\ServiceContainer;
use Temporal\Worker\LoopInterface;
use Temporal\Workflow\ProcessInterface;
use Temporal\Workflow\WorkflowContext;

class Process extends Scope implements ProcessInterface
{
    /**
     * Process constructor.
     * @param ServiceContainer $services
     * @param WorkflowContext $ctx
     */
    public function __construct(ServiceContainer $services, WorkflowContext $ctx)
    {
        parent::__construct($services, $ctx);

        $this->getWorkflowInstance()->getSignalQueue()->onSignal(
            function (callable $handler) {
                $scope = $this->createScope(true, LoopInterface::ON_SIGNAL);
                $scope->onClose(
                    function (?\Throwable $error) {
                        if ($error !== null) {
                            // we want to fail process when signal scope fails
                            $this->complete($error);
                        }
                    }
                );

                try {
                    $scope->start($handler);
                } catch (InvalidArgumentException $e) {
                    // invalid signal invocation, destroy the scope with no traces
                    $scope->unlock();
                }
            }
        );

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
     * @param callable $handler
     * @param ValuesInterface|null $values
     */
    public function start(callable $handler, ValuesInterface $values = null)
    {
        try {
            $this->makeCurrent();
            $this->context->getWorkflowInstance()->initConstructor();
            parent::start($handler, $values);
        } catch (\Throwable $e) {
            $this->complete($e);
        }
    }

    /**
     * @return mixed|string
     */
    public function getID()
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
        if ($result instanceof \Throwable) {
            if ($result instanceof DestructMemorizedInstanceException) {
                // do not handle
                return;
            }

            $this->context->complete([], $result);
            return;
        }

        if ($this->context->isContinuedAsNew()) {
            return;
        }

        $this->context->complete($result);
    }
}
