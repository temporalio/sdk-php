<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\DataConverter;

use Temporal\Api\Common\V1\Payload;
use Temporal\Exception\DataConverterException;

interface DataConverterInterface
{
    /**
     * @param Payload $payload
     * @param string|\ReflectionClass|\ReflectionType|Type|null $type
     * @return mixed
     *
     * @psalm-mutation-free
     * @throws DataConverterException
     */
    public function fromPayload(Payload $payload, $type);

    /**
     * @param mixed $value
     * @return Payload
     *
     * @throws DataConverterException
     */
    public function toPayload($value): Payload;
}
