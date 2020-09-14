<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Client\Router;

use Spiral\Core\Container;
use Temporal\Client\Common\ClassInfo;
use Temporal\Client\Transport\Request\Request;
use Temporal\Client\WorkerInterface;

abstract class Route implements RouteInterface
{
    /**
     * @var string
     */
    protected string $basename;

    /**
     * @var Container
     */
    protected Container $app;

    /**
     * @var WorkerInterface
     */
    protected WorkerInterface $worker;

    /**
     * @param Container $app
     * @param WorkerInterface $worker
     */
    public function __construct(Container $app, WorkerInterface $worker)
    {
        $this->app = $app;
        $this->worker = $worker;

        $this->basename = ClassInfo::name(static::class);
    }

    /**
     * @return string
     */
    public function getName(): string
    {
        return $this->basename;
    }
}
