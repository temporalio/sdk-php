<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Tests\Unit\DTO\Type\EnumType\Stub;

use Temporal\Internal\Marshaller\Meta\Marshal;
use Temporal\Internal\Marshaller\Type\EnumValueType;

class EnumValueDto
{
    #[Marshal(name: 'scalarEnum', type: EnumValueType::class, of: ScalarEnum::class)]
    public ScalarEnum $scalarEnum;

    public ScalarEnum $autoScalarEnum;

    public ?ScalarEnum $nullable;
}
