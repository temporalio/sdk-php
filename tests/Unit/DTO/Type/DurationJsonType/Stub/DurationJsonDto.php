<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Tests\Unit\DTO\Type\DurationJsonType\Stub;

use Google\Protobuf\Duration;
use Temporal\Internal\Marshaller\Meta\Marshal;

class DurationJsonDto
{
    public \DateInterval $duration;

    #[Marshal('duration_proto', of: Duration::class)]
    public \DateInterval $durationProto;
}
