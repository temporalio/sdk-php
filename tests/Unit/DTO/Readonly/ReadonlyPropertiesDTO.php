<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Tests\Unit\DTO\Readonly;

use Temporal\Tests\Unit\DTO\Enum\ScalarEnum;

class ReadonlyPropertiesDTO
{
    readonly public string $propertiesString;

    public function __construct(
        readonly public string $promotedString,
        readonly public string $secondPromotedString,
        string $propertiesString
    )
    {
        $this->propertiesString = $propertiesString;
    }
}
