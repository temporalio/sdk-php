<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Tests\Fixtures;

use Temporal\Worker\Transport\Command\Client\Request;

class CommandResetter extends Request
{
    public static function reset()
    {
        self::$lastID = 9000;
    }
}
