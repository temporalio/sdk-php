<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Internal\Workflow;

use React\Promise\PromiseInterface;
use Temporal\Internal\Declaration\Prototype\WorkflowPrototype;
use Temporal\Internal\Support\Reflection;
use Temporal\Workflow\ContinueAsNewOptions;
use Temporal\Workflow\WorkflowContextInterface;

class ContinueAsNewProxy extends Proxy
{
    /**
     * @var string
     */
    private const ERROR_UNDEFINED_WORKFLOW_METHOD =
        'The given stub class "%s" does not contain a workflow method named "%s"';

    /**
     * @var string
     */
    private const ERROR_ALREADY_CONTINUED =
        'Workflow "%s" has already been called within this "continue as new" stub';

    private string $class;

    private WorkflowPrototype $workflow;

    private ContinueAsNewOptions $options;

    private WorkflowContextInterface $context;

    private bool $isContinued = false;

    public function __construct(
        string $class,
        WorkflowPrototype $workflow,
        ContinueAsNewOptions $options,
        WorkflowContextInterface $context,
    ) {
        $this->class = $class;
        $this->workflow = $workflow;
        $this->options = $options;
        $this->context = $context;
    }

    /**
     * @return PromiseInterface
     */
    public function __call(string $method, array $args)
    {
        if ($this->isContinued()) {
            throw new \BadMethodCallException(
                \sprintf(self::ERROR_ALREADY_CONTINUED, $this->workflow->getID()),
            );
        }

        $handler = $this->workflow->getHandler();

        if ($method !== $handler?->getName()) {
            throw new \BadMethodCallException(
                \sprintf(self::ERROR_UNDEFINED_WORKFLOW_METHOD, $this->class, $method),
            );
        }

        $this->isContinued = true;

        if ($handler !== null) {
            $args = Reflection::orderArguments($handler, $args);
        }

        return $this->context->continueAsNew($this->workflow->getID(), $args, $this->options);
    }

    private function isContinued(): bool
    {
        return $this->isContinued;
    }
}
