<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Nexus;

use Temporal\Client\WorkflowOptions;

/**
 * Description of the workflow that backs a Nexus operation.
 *
 * Built by the user inside a `WorkflowRunOperation::fromWorkflowMethod()`
 * factory; the runtime layers Nexus-specific options (request id, completion
 * callback, task-queue defaults) on top.
 *
 * @since Nexus support
 */
final class WorkflowHandle
{
    /**
     * @param class-string $workflowClass Workflow interface annotated with #[WorkflowInterface].
     * @param list<mixed> $args Arguments forwarded to the workflow method.
     */
    public function __construct(
        public readonly string $workflowClass,
        public readonly WorkflowOptions $options,
        public readonly array $args,
    ) {}

    /**
     * @param class-string $workflowClass
     */
    public static function fromWorkflowMethod(
        string $workflowClass,
        WorkflowOptions $options,
        mixed ...$args,
    ): self {
        return new self($workflowClass, $options, \array_values($args));
    }
}
