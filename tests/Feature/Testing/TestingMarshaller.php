<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Tests\Feature\Testing;

use Spiral\Attributes\AttributeReader;
use Temporal\Internal\Marshaller\Mapper\AttributeMapperFactory;
use Temporal\Internal\Marshaller\Mapper\MapperFactoryInterface;
use Temporal\Internal\Marshaller\Marshaller;
use Temporal\Internal\Marshaller\MarshallerInterface;

/**
 * @implements MarshallerInterface<array>
 */
class TestingMarshaller implements MarshallerInterface
{
    /**
     * @var Marshaller
     */
    private Marshaller $marshaller;

    /**
     * @param MapperFactoryInterface|null $mapper
     */
    public function __construct(MapperFactoryInterface $mapper = null)
    {
        $mapper ??= new AttributeMapperFactory(new AttributeReader());

        $this->marshaller = new Marshaller($mapper);
    }

    public function marshal(object $from): array
    {
        return $this->marshaller->marshal($from);
    }

    public function unmarshal(array $from, object $to): object
    {
        return $this->marshaller->unmarshal($from, $to);
    }
}
