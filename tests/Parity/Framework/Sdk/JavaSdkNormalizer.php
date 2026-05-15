<?php

declare(strict_types=1);

namespace Temporal\Tests\Parity\Framework\Sdk;

use Temporal\Tests\Parity\Framework\Source;

/**
 * Normalizes a Java-SDK-captured event. Java-specific quirks:
 *  - worker `identity` is `<pid>@<host>` (no `roadrunner:` prefix)
 *  - `sdkMetadata.sdkName` is `temporal-java`
 *  - task-queue name is the raw caller-provided string
 *
 * All shared field rules cover these. The class exists as a stable identity
 * for `Source::JAVA` dispatch.
 */
final class JavaSdkNormalizer extends AbstractSdkNormalizer
{
    protected function source(): Source
    {
        return Source::JAVA;
    }
}
