<?php

declare(strict_types=1);

namespace Temporal\Tests\Parity\Framework\Sdk;

use Temporal\Tests\Parity\Framework\Source;

final class GoSdkNormalizer extends AbstractSdkNormalizer
{
    protected function source(): Source
    {
        return Source::GO;
    }

    protected function dropEventTypes(): array
    {
        return [
            'EVENT_TYPE_UPSERT_WORKFLOW_SEARCH_ATTRIBUTES',
        ];
    }
}
