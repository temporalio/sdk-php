<?php

declare(strict_types=1);

namespace Temporal\Tests\Parity\Framework\Sdk;

use Temporal\Tests\Parity\Framework\Source;

/**
 * Normalizes a PHP-SDK-captured event. PHP-specific quirks:
 *  - worker `identity` is `roadrunner:<task-queue>:<uuid>`
 *  - `sdkMetadata.sdkName` is always `temporal-go` (RR runs the Go SDK)
 *  - task-queue name is derived from a PHP class FQN
 *
 * All such quirks are handled by the shared field rules; nothing extra is
 * needed here today. The class still exists as a stable identity so the
 * registry can dispatch by `Source::PHP`.
 */
final class PhpSdkNormalizer extends AbstractSdkNormalizer
{
    protected function source(): Source
    {
        return Source::PHP;
    }
}
