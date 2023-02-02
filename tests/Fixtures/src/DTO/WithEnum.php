<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Tests\DTO;

use Temporal\Tests\Unit\DTO\Type\EnumType\Stub\SimpleEnum;

class WithEnum
{
    public function __construct(
        public SimpleEnum $simple,
    ) {
    }
}
