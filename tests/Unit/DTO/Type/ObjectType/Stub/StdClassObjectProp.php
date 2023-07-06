<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Tests\Unit\DTO\Type\ObjectType\Stub;

use stdClass;

final class StdClassObjectProp
{
    public function __construct(
        public object $object,
        public stdClass $class,
    ) {
    }
}
