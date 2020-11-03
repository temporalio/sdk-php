<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Client\Worker;

final class Event
{
    /**
     * @var string
     */
    public const ON_SIGNAL = 'signal';

    /**
     * @var string
     */
    public const ON_CALLBACK = 'callback';

    /**
     * @var string
     */
    public const ON_TICK = 'tick';

    /**
     * Fires after decoding and before processing all incoming messages.
     *
     * @var string
     */
    public const ON_RECEIVED = 'received';

    /**
     * Fires after processing all incoming messages and before result encoding.
     *
     * @var string
     */
    public const ON_PROCEED = 'proceed';
}
