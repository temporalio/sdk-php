<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

use Spiral\Goridge\Relay;
use Temporal\Client;
use Temporal\Worker\Transport\Goridge;

require __DIR__ . '/../../vendor/autoload.php';

$client = Client::create(new Goridge(Relay::create('tcp://127.0.0.1:6001')));

$client->reload();
