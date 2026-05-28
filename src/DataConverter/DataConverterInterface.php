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

/**
 * @psalm-import-type TType from Type
 */
interface DataConverterInterface
{
    /**
     * @param TType $type
     * @return mixed
     *
     * @throws DataConverterException
     */
    public function fromPayload(Payload $payload, mixed $type);

    /**
     * @throws DataConverterException
     */
    public function toPayload(mixed $value): Payload;
}
