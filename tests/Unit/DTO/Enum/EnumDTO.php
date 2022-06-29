<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Tests\Unit\DTO\Enum;

use Temporal\Internal\Marshaller\Meta\Marshal;
use Temporal\Internal\Marshaller\Type\EnumType;

class EnumDTO
{
    #[Marshal(name: 'simpleEnum', type: EnumType::class, of: SimpleEnum::class)]
    public SimpleEnum $simpleEnum;

    #[Marshal(name: 'scalarEnum', type: EnumType::class, of: ScalarEnum::class)]
    public ScalarEnum $scalarEnum;
}
