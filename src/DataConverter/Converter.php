<?php

declare(strict_types=1);

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Temporal\DataConverter;

use Temporal\Api\Common\V1\Payload;

abstract class Converter implements PayloadConverterInterface
{
    /**
     * @param string $data
     * @return Payload
     */
    protected function create(string $data): Payload
    {
        $payload = new Payload();
        $payload->setMetadata([EncodingKeys::METADATA_ENCODING_KEY => $this->getEncodingType()]);
        $payload->setData($data);

        return $payload;
    }
}
