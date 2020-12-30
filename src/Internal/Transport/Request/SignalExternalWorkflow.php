<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Internal\Transport\Request;

use Temporal\Worker\Command\Request;
use Temporal\Workflow\ChildWorkflowOptions;

final class SignalExternalWorkflow extends Request
{
    /**
     * @var string
     */
    public const NAME = 'SignalExternalWorkflow';

    /**
     * SignalExternalWorkflow constructor.
     *
     * @param ChildWorkflowOptions $options
     * @param string $runId
     * @param string $signal
     * @param array $args
     */
    public function __construct(ChildWorkflowOptions $options, string $runId, string $signal, array $args = [])
    {
        parent::__construct(self::NAME, [
            'namespace'         => $options->namespace,
            'workflowID'        => $options->workflowId,
            'runID'             => $runId,
            'signal'            => $signal,
            'childWorkflowOnly' => true,
            'args'              => $args,
        ]);
    }
}
