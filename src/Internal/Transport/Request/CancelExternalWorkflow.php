<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Internal\Transport\Request;

use Temporal\Worker\Transport\Command\Request;

/**
 * @psalm-immutable
 */
class CancelExternalWorkflow extends Request
{
    public const NAME = 'CancelExternalWorkflow';

    /**
     * @param string $namespace
     * @param string $workflowId
     * @param string|null $runId
     */
    public function __construct(
        private string $namespace,
        private string $workflowId,
        private ?string $runId,
    ) {
        $options = [
            'namespace' => $namespace,
            'workflowID' => $workflowId,
            'runID' => $runId,
        ];

        parent::__construct(self::NAME, $options, null);
    }

    /**
     * @return string
     */
    public function getNamespace(): string
    {
        return $this->namespace;
    }

    /**
     * @return string
     */
    public function getWorkflowId(): string
    {
        return $this->workflowId;
    }

    /**
     * @return null|string
     */
    public function getRunId(): ?string
    {
        return $this->runId;
    }
}
