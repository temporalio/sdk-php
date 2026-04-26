<?php

declare(strict_types=1);

namespace Temporal\Nexus;

use Temporal\Client\WorkflowOptions;

/**
 * Description of the workflow that backs a Nexus operation.
 *
 * `fromWorkflowMethod()` is the user-facing factory. It captures the workflow
 * class, the {@see WorkflowOptions} the handler wants to start it with, and
 * the arguments to pass to the workflow method.
 *
 * The Nexus runtime ({@see WorkflowRunOperation}) then layers in
 * Nexus-specific options on top — request id, completion callback,
 * task-queue defaults — so the handler does not have to wire those
 * itself.
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
     * Builds a handle for an annotated workflow class. Mirrors Java's
     * `WorkflowHandle.fromWorkflowMethod(stub::method, input)` — the missing
     * piece is the method reference (PHP has none), which is implicit
     * because PHP `WorkflowClient::newWorkflowStub()` already binds to the
     * single `#[WorkflowMethod]` on the interface.
     *
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
