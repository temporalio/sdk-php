<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Tests\Nexus\Fixture\ServiceHandler;

use Temporal\Tests\Nexus\Fixture\Service\VoidServiceInterface;

final class VoidServiceImpl implements VoidServiceInterface
{
    public function operation(): void {}
}
