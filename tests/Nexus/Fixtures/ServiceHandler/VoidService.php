<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Tests\Nexus\Fixtures\ServiceHandler;

use Temporal\Tests\Nexus\Fixtures\Service\VoidServiceInterface;

final class VoidService implements VoidServiceInterface
{
    public function operation(): void {}
}
