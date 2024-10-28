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
use Temporal\Worker\Transport\Command\Client\Request;

/**
 * @psalm-immutable
 */
final class SignalExternalWorkflow extends Request
{
    public const NAME = 'SignalExternalWorkflow';

    public function __construct(
        string $namespace,
        string $workflowId,
        ?string $runId,
        string $signal,
        ValuesInterface $input = null,
        bool $childWorkflowOnly = false,
    ) {
        $options = [
            'namespace' => $namespace,
            'workflowID' => $workflowId,
            'runID' => $runId,
            'signal' => $signal,
            'childWorkflowOnly' => $childWorkflowOnly,
        ];

        parent::__construct(self::NAME, $options, $input);
    }
}
