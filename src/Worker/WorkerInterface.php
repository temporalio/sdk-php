<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Client\Worker;

use Evenement\EventEmitterInterface;
use Temporal\Client\Internal\Transport\DispatcherInterface;

/**
 * @implements EventEmitterInterface<WorkerInterface::ON_*>
 */
interface WorkerInterface extends DispatcherInterface, EventEmitterInterface
{
    /**
     * @var string
     */
    public const ON_SIGNAL = 'signal';

    /**
     * @var string
     */
    public const ON_QUERY = 'query';

    /**
     * @var string
     */
    public const ON_CALLBACK = 'callback';

    /**
     * @var string
     */
    public const ON_TICK = 'tick';

    /**
     * @return string
     */
    public function getTaskQueue(): string;

    /**
     * @param class-string $class
     * @param bool $overwrite
     * @return $this
     */
    public function registerWorkflow(string $class, bool $overwrite = false): self;

    /**
     * @param class-string $class
     * @param bool $overwrite
     * @return $this
     */
    public function registerActivity(string $class, bool $overwrite = false): self;
}
