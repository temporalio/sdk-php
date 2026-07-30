<?php

declare(strict_types=1);

namespace Temporal\Testing\Interactions;

use Temporal\Api\Common\V1\Payloads;

/**
 * @psalm-immutable
 */
final class RecordedCall
{
    public function __construct(
        public readonly RecordedCallKind $kind,
        public readonly string $name,
        public readonly ?Payloads $input,
        public readonly ?int $durationMs,
    ) {}
}
