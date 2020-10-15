<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Client;

use Temporal\Client\Worker\Factory;
use Temporal\Client\Workflow\WorkflowTransportInterface;
use Temporal\Client\Workflow\WorkflowWorkerInterface;

/**
 * Worker factory facade
 */
final class Worker
{
    private static ?Factory $factory = null;

    /**
     * @param Factory $factory
     */
    public static function setFactory(Factory $factory): void
    {
        self::$factory = $factory;
    }

    /**
     * @return Factory
     */
    protected static function factory(): Factory
    {
        return self::$factory ??= new Factory();
    }

    /**
     * @param WorkflowTransportInterface $transport
     * @return WorkflowWorkerInterface
     * @throws \Exception
     */
    public static function forWorkflows(WorkflowTransportInterface $transport): WorkflowWorkerInterface
    {
        return (new Factory())->forWorkflows($transport);
    }
}
