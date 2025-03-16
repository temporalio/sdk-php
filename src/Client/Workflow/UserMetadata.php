<?php

declare(strict_types=1);

namespace Temporal\Client\Workflow;

use JetBrains\PhpStorm\Immutable;

/**
 * Information a user can set, often for use by user interfaces.
 *
 * @see \Temporal\Api\Sdk\V1\UserMetadata
 * @psalm-immutable
 */
#[Immutable]
final class UserMetadata
{
    public function __construct(
        /**
         * Short-form text that provides a summary. This payload should be a "json/plain"-encoded payload
         * that is a single JSON string for use in user interfaces. User interface formatting may not
         * apply to this text when used in "title" situations. The payload data section is limited to 400
         * bytes by default.
         */
        public readonly string $summary,

        /**
         * Long-form text that provides details. This payload should be a "json/plain"-encoded payload
         * that is a single JSON string for use in user interfaces. User interface formatting may apply to
         * this text in common use. The payload data section is limited to 20000 bytes by default.
         */
        public readonly string $details,
    ) {}
}
