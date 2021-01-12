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

    /**
     * @var string
     */
    private string $class;

    /**
     * @var WorkflowPrototype[]
     */
    private array $workflows;

    /**
     * @var ContinueAsNewOptions
     */
    private ContinueAsNewOptions $options;

    /**
     * @var WorkflowContextInterface
     */
    private WorkflowContextInterface $context;

    /**
     * @var WorkflowPrototype|null
     */
    private ?WorkflowPrototype $prototype = null;

    /**
     * @param string $class
     * @param array<WorkflowPrototype> $workflows
     * @param ContinueAsNewOptions $options
     * @param WorkflowContextInterface $context
     */
    public function __construct(
        string $class,
        array $workflows,
        ContinueAsNewOptions $options,
        WorkflowContextInterface $context
    ) {
        $this->class = $class;
        $this->workflows = $workflows;
        $this->options = $options;
        $this->context = $context;
    }

    /**
     * @param string $method
     * @param array $args
     * @return PromiseInterface
     */
    public function __call(string $method, array $args)
    {
        $prototype = $this->findPrototypeByHandlerNameOrFail($method);

        if ($this->isContinued()) {
            throw new \BadMethodCallException(
                \sprintf(self::ERROR_ALREADY_CONTINUED, $this->prototype->getID())
            );
        }

        $this->prototype = $prototype;

        return $this->context->continueAsNew($prototype->getID(), $args, $this->options);
    }

    /**
     * @param string $name
     * @return WorkflowPrototype
     */
    private function findPrototypeByHandlerNameOrFail(string $name): WorkflowPrototype
    {
        $prototype = $this->findPrototypeByHandlerName($this->workflows, $name);

        if ($prototype === null) {
            throw new \BadMethodCallException(
                \sprintf(self::ERROR_UNDEFINED_WORKFLOW_METHOD, $this->class, $name)
            );
        }

        return $prototype;
    }

    /**
     * @return bool
     */
    private function isContinued(): bool
    {
        return $this->prototype !== null;
    }
}
