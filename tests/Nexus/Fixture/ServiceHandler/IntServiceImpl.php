<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Tests\Nexus\Fixture\ServiceHandler;

use Temporal\Tests\Nexus\Fixture\Service\IntServiceInterface;

final class IntServiceImpl implements IntServiceInterface
{
    public function operation(int $input): int
    {
        return 0;
    }
}
