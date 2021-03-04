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

interface PayloadConverterInterface
{
    /**
     * Returns associated encoding type.
     *
     * @return string
     */
    public function getEncodingType(): string;

    /**
     * Implements conversion of a single value to Payload. Must return null if value can't be encoded.
     *
     * @param mixed $value
     * @return Payload|null
     *
     * @throws DataConverterException
     */
    public function toPayload($value): ?Payload;

    /**
     * @param Payload $payload
     * @param Type $type
     * @return mixed
     *
     * @throws DataConverterException
     */
    public function fromPayload(Payload $payload, Type $type);
}
