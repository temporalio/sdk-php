<?php

declare(strict_types=1);

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Temporal\Interceptor;

use Temporal\Api\Common\V1\Header as ProtoHeader;
use Temporal\DataConverter\EncodedCollection;

final class Header extends EncodedCollection implements HeaderInterface
{
    /**
     * Build a {@see ProtoHeader} message.
     *
     * @internal
     */
    public function toHeader(): ProtoHeader
    {
        return new ProtoHeader(['fields' => $this->toPayloadArray()]);
    }
}
