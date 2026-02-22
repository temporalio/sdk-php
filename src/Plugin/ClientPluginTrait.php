<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Plugin;

/**
 * No-op defaults for {@see ClientPluginInterface}.
 *
 * @implements ClientPluginInterface
 */
trait ClientPluginTrait
{
    public function configureClient(ClientPluginContext $context): void
    {
        // no-op
    }
}
