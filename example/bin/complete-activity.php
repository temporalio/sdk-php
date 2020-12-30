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

$result = $client->completeActivity('ACTIVITY_TASK_TOKEN', 'Custom Activity Result');

dump($result);
