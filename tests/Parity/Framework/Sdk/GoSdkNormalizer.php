<?php

declare(strict_types=1);

namespace Temporal\Tests\Parity\Framework\Sdk;

use Temporal\Tests\Parity\Framework\Source;

/**
 * Normalizes a Go-SDK-captured event. Placeholder for future scenarios that
 * compare Go directly against another SDK; no Go-specific overrides are
 * needed today (the shared field rules cover everything seen so far).
 */
final class GoSdkNormalizer extends AbstractSdkNormalizer
{
    protected function source(): Source
    {
        return Source::GO;
    }
}
