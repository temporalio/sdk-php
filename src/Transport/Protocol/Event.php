<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Client\Transport\Protocol;

final class Event
{
    /**
     * @var string
     */
    public const ON_ENCODING = 'encoding';

    /**
     * @var string
     */
    public const ON_ENCODED = 'encoded';

    /**
     * @var string
     */
    public const ON_DECODING = 'decoding';

    /**
     * @var string
     */
    public const ON_DECODED = 'decoded';

}
