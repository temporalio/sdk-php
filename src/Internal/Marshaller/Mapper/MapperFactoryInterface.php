<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Internal\Marshaller\Mapper;

use Temporal\Internal\Marshaller\TypeFactoryInterface;

interface MapperFactoryInterface
{
    public function create(\ReflectionClass $class, TypeFactoryInterface $types): MapperInterface;
}
