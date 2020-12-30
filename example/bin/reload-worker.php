<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

use Temporal\Client;
use Temporal\Worker\Transport\RoadRunner;

require __DIR__ . '/../../vendor/autoload.php';

$client = Client::create(RoadRunner::socket(6001));

foreach ($client->reload(Client\ReloadGroup::GROUP_ALL) as $response) {
    dump($response);
}
