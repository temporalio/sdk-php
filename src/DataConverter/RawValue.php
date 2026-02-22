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

final class RawValue
{
    private Payload $payload;

    public function __construct(Payload $data)
    {
        $this->payload = $data;
    }

    public function getPayload(): Payload
    {
        return $this->payload;
    }
}
