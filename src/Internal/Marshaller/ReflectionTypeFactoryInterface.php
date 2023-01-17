<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Internal\Marshaller;

use Temporal\Internal\Marshaller\Type\TypeDto;

interface ReflectionTypeFactoryInterface extends TypeFactoryInterface
{
    /**
     * @param \ReflectionProperty $property
     *
     * @return null|TypeDto
     */
    public function detectType(\ReflectionProperty $property): ?TypeDto;
}
