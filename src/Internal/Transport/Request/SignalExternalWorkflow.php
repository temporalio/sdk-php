<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Internal\Transport\Request;

use Temporal\DataConverter\ValuesInterface;
use Temporal\Worker\Transport\Command\Request;

final class SignalExternalWorkflow extends Request
{
    public const NAME = 'SignalExternalWorkflow';

    /**
     * @param string $namespace
     * @param string $workflowId
     * @param string $runId
     * @param string $signal
     * @param ValuesInterface $input
     */
    public function __construct(
        string $namespace,
        string $workflowId,
        string $runId,
        string $signal,
        ValuesInterface $input = null
    ) {
        parent::__construct(
            self::NAME,
            [
                'namespace' => $namespace,
                'workflowID' => $workflowId,
                'runID' => $runId,
                'signal' => $signal,
                'childWorkflowOnly' => true,
            ],
            $input
        );
    }
}
