<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Worker\Core;

use Coresdk\Workflow_commands\SetPatchMarker;
use Coresdk\Workflow_commands\WorkflowCommand;

/**
 * @internal
 */
final class PatchVersions
{
    private const DEFAULT_VERSION = -1;
    private const PATCH_ID = '/^(.+)-(-?\d+)$/';

    /** @var array<string, int> */
    private array $notified = [];

    /** @var array<string, int> */
    private array $versions = [];

    public function notify(string $patchId): void
    {
        if (\preg_match(self::PATCH_ID, $patchId, $match) === 1) {
            $this->notified[$match[1]] = (int) $match[2];
        }
    }

    /**
     * @param list<WorkflowCommand> $commands
     */
    public function version(string $changeId, int $minSupported, int $maxSupported, bool $isReplaying, array &$commands): int
    {
        if (!isset($this->versions[$changeId])) {
            $version = $this->notified[$changeId] ?? ($isReplaying ? self::DEFAULT_VERSION : $maxSupported);
            $this->versions[$changeId] = $version;
            if ($version !== self::DEFAULT_VERSION) {
                $commands[] = new WorkflowCommand(['set_patch_marker' => new SetPatchMarker(['patch_id' => $changeId . '-' . $version])]);
            }
        }

        $version = $this->versions[$changeId];
        if ($version < $minSupported) {
            throw new \LogicException(\sprintf(
                'Workflow code removed support of version %d for "%s" changeID. The oldest supported version is %d',
                $version,
                $changeId,
                $minSupported,
            ));
        }
        if ($version > $maxSupported) {
            throw new \LogicException(\sprintf(
                'Workflow code is too old to support version %d for "%s" changeID. The maximum supported version is %d',
                $version,
                $changeId,
                $maxSupported,
            ));
        }

        return $version;
    }
}
