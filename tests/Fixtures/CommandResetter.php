<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Tests\Fixtures;

use Temporal\Worker\Transport\Command\Command;

class CommandResetter extends Command
{
    public static function reset()
    {
        self::$lastID = 9000;
    }
}
