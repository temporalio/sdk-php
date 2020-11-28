<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Client\Activity;

use Temporal\Client\Internal\Marshaller\Meta\Marshal;

/**
 * ActivityType identifies a activity type.
 */
class ActivityType
{
    /**
     * @readonly
     * @psalm-allow-private-mutation
     * @var string
     */
    #[Marshal(name: 'Name')]
    public string $name = '';
}
