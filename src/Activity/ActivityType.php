<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Activity;

use Temporal\Internal\Marshaller\Meta\Marshal;

/**
 * ActivityType identifies a activity type.
 */
class ActivityType
{
    /**
     * @psalm-readonly
     * @psalm-allow-private-mutation
     * @var string
     */
    #[Marshal(name: 'Name')]
    public string $name = '';
}
