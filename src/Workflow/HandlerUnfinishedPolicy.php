<?php

declare(strict_types=1);

namespace Temporal\Workflow;

/**
 * Actions taken if a workflow terminates with running handlers.
 *
 * Policy defining actions taken when a workflow exits while update or signal handlers are running.
 * The workflow exit may be due to successful return, failure, cancellation, or continue-as-new.
 */
enum HandlerUnfinishedPolicy
{
    /**
     * Issue a warning in addition to abandoning.
     */
    case WarnAndAbandon;

    /**
     * Abandon the handler.
     *
     * In the case of an update handler, this means that the client will receive an error rather than
     * the update result.
     */
    case Abandon;
}
