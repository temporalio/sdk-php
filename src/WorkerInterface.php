<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Client;

use Temporal\Client\Worker\ActivityProviderInterface;
use Temporal\Client\Worker\ExecutorInterface;
use Temporal\Client\Worker\WorkflowProviderInterface;

interface WorkerInterface extends WorkflowProviderInterface, ActivityProviderInterface, ExecutorInterface
{
}
