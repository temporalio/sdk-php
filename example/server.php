<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

use App\ServerEmulator;
use Monolog\Handler\StreamHandler;
use Monolog\Logger;
use React\EventLoop\Factory;

require __DIR__ . '/../vendor/autoload.php';

$logger = new Logger('Temporal', [
    new StreamHandler(\STDOUT)
]);

$server = new ServerEmulator(Factory::create());
$server->listen(8080);

$server->run();
