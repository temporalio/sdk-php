<?php

declare(strict_types=1);

namespace Temporal\Client\Workflow;

use Temporal\Internal\Marshaller\Meta\Marshal;
use Temporal\Internal\Traits\CloneWith;

/**
 * Information a user can set, often for use by user interfaces.
 *
 * @see \Temporal\Api\Sdk\V1\UserMetadata
 */
final class UserMetadata
{
    use CloneWith;

    public function __construct(
        /**
         * Short-form text that provides a summary. This payload should be a "json/plain"-encoded payload
         * that is a single JSON string for use in user interfaces. User interface formatting may not
         * apply to this text when used in "title" situations. The payload data section is limited to 400
         * bytes by default.
         */
        #[Marshal(name: 'summary')]
        public readonly string $summary,

        /**
         * Long-form text that provides details. This payload should be a "json/plain"-encoded payload
         * that is a single JSON string for use in user interfaces. User interface formatting may apply to
         * this text in common use. The payload data section is limited to 20000 bytes by default.
         */
        #[Marshal(name: 'details')]
        public readonly string $details,
    ) {}

    public function withSummary(string $summary): self
    {
        /** @see self::$summary */
        return $this->cloneWith('summary', $summary);
    }

    public function withDetails(string $details): self
    {
        /** @see self::$details */
        return $this->cloneWith('details', $details);
    }
}
