<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Client;

if (!\class_exists(\Temporal\Client\Common\ServerCapabilities::class)) {
    /**
     * @deprecated use {@see \Temporal\Client\Common\ServerCapabilities} instead. Will be removed in the future.
     */
    class ServerCapabilities
    {
    }
}
