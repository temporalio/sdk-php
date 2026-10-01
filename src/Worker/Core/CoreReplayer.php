<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Worker\Core;

use Temporal\Internal\Bridge\Bridge;
use Coresdk\Workflow_activation\RemoveFromCache\EvictionReason;
use Coresdk\Workflow_activation\WorkflowActivation;
use Temporal\Api\History\V1\History;

/**
 * @internal
 */
final class CoreReplayer
{
    private const IDLE_TIMEOUT_SECONDS = 60;
    private const EXPECTED_EVICTIONS = [
        EvictionReason::CACHE_FULL,
        EvictionReason::LANG_REQUESTED,
        EvictionReason::WORKFLOW_EXECUTION_ENDING,
    ];

    private static int $tag = 0;

    public function __construct(
        private readonly Bridge $bridge,
    ) {}

    public function replay(History $history, string $workflowId, array $config, WorkflowActivations $activations): void
    {
        $core = $this->bridge->newReplayer($config, $history->serializeToString(), $workflowId);
        $tag = ++self::$tag;
        try {
            $failure = $this->drain($core, $tag, $activations);
        } finally {
            $errors = $this->bridge->finalizeWorkers([$tag => $core]);
            $this->bridge->freeWorker($core);
        }

        if ($failure !== null) {
            throw $failure;
        }
        if ($errors !== []) {
            throw new ReplayFailedException('The replay worker did not finalize: ' . \implode('; ', $errors), false);
        }
    }

    private static function evictionFailure(string $data): ?ReplayFailedException
    {
        $activation = new WorkflowActivation();
        $activation->mergeFromString($data);
        $failure = null;
        foreach ($activation->getJobs() as $job) {
            $eviction = $job->getRemoveFromCache();
            if ($eviction !== null && !\in_array($eviction->getReason(), self::EXPECTED_EVICTIONS, true)) {
                $failure = new ReplayFailedException($eviction->getMessage(), $eviction->getReason() === EvictionReason::NONDETERMINISM);
            }
        }

        return $failure;
    }

    private function drain(\FFI\CData $core, int $tag, WorkflowActivations $activations): ?ReplayFailedException
    {
        $failure = null;
        $deadline = \microtime(true) + self::IDLE_TIMEOUT_SECONDS;
        $this->bridge->pollWorkflowActivation($core, $tag);
        while (\microtime(true) < $deadline) {
            foreach ($this->bridge->nextEvents(Bridge::POLL_TIMEOUT_MS) as [$eventTag, $kind, $status, $data]) {
                if ($eventTag !== $tag) {
                    continue;
                }
                $deadline = \microtime(true) + self::IDLE_TIMEOUT_SECONDS;
                if ($kind === Bridge::KIND_WORKFLOW_COMPLETED) {
                    throw new \RuntimeException('sdk-core completion failed: ' . $data);
                }
                if ($kind !== Bridge::KIND_WORKFLOW_ACTIVATION) {
                    continue;
                }
                if ($status === Bridge::STATUS_SHUTDOWN) {
                    return $failure;
                }
                if ($status !== Bridge::STATUS_OK) {
                    throw new \RuntimeException('sdk-core poll failed: ' . $data);
                }

                $failure = self::evictionFailure($data) ?? $failure;
                $this->bridge->completeWorkflowActivation($core, $tag, $activations->handle($data));
                $this->bridge->pollWorkflowActivation($core, $tag);
            }
        }

        return $failure ?? new ReplayFailedException(\sprintf('The replay got no activation for %d seconds', self::IDLE_TIMEOUT_SECONDS), false);
    }
}
