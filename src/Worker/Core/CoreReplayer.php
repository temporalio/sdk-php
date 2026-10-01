<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Worker\Core;

use Coresdk\Workflow_activation\RemoveFromCache\EvictionReason;
use Coresdk\Workflow_activation\WorkflowActivation;
use Temporal\Api\History\V1\History;

/**
 * @internal
 */
final class CoreReplayer
{
    private const TIMEOUT_SECONDS = 60;
    private const EXPECTED_EVICTIONS = [
        EvictionReason::CACHE_FULL,
        EvictionReason::LANG_REQUESTED,
        EvictionReason::WORKFLOW_EXECUTION_ENDING,
    ];

    private static int $tag = 0;

    public function replay(History $history, string $workflowId, array $config, WorkflowActivations $activations): void
    {
        $bridge = Bridge::shared();
        $core = $bridge->newReplayer($config, $history->serializeToString(), $workflowId);
        $tag = ++self::$tag;
        try {
            $failure = $this->drain($bridge, $core, $tag, $activations);
        } finally {
            $bridge->finalizeWorkers([$tag => $core]);
            $bridge->freeWorker($core);
        }

        if ($failure !== null) {
            throw $failure;
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

    private function drain(Bridge $bridge, \FFI\CData $core, int $tag, WorkflowActivations $activations): ?ReplayFailedException
    {
        $failure = null;
        $deadline = \microtime(true) + self::TIMEOUT_SECONDS;
        $bridge->pollWorkflowActivation($core, $tag);
        while (\microtime(true) < $deadline) {
            foreach ($bridge->nextEvents(Bridge::POLL_TIMEOUT_MS) as [$eventTag, $kind, $status, $data]) {
                if ($eventTag !== $tag) {
                    continue;
                }
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
                $bridge->completeWorkflowActivation($core, $tag, $activations->handle($data));
                $bridge->pollWorkflowActivation($core, $tag);
            }
        }

        return new ReplayFailedException(\sprintf('The replay did not finish in %d seconds', self::TIMEOUT_SECONDS), false);
    }
}
