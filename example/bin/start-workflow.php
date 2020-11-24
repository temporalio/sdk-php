<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

use Spiral\Goridge\RPC;

/** @var RPC $rpc */
$rpc = require __DIR__ . '/connection.php';

$rpc->call('resetter.Reset', 'workflows');
$rpc->call('resetter.Reset', 'activities');

$result = $rpc->call('temporal.ExecuteWorkflow', [
    'name'    => 'CancellableWorkflow',
    'input'   => [],
    'options' => [
        'taskQueue'                => 'default',
        'workflowExecutionTimeout' => '60s',
        'workflowRunTimeout'       => '60s',
        'workflowTaskTimeout'      => '60s',
    ],
]);

dump($result);
