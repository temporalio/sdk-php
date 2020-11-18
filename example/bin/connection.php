<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

use Spiral\Goridge\RPC;
use Spiral\Goridge\SocketRelay;

require __DIR__ . '/../../vendor/autoload.php';

return new RPC(new SocketRelay('localhost', 6001));
