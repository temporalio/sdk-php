<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Client\Activity;

use Temporal\Client\Worker\Declaration\Repository\ActivityRepositoryInterface;
use Temporal\Client\Worker\Declaration\Repository\ActivityRepositoryTrait;
use Temporal\Client\Meta\ReaderInterface;
use Temporal\Client\Worker\EmitterInterface;

/**
 * @noinspection PhpSuperClassIncompatibleWithInterfaceInspection
 */
class ActivityWorker implements ActivityRepositoryInterface, EmitterInterface
{
    use ActivityRepositoryTrait;

    /**
     * @var ReaderInterface
     */
    private ReaderInterface $reader;

    /**
     * @param ReaderInterface $reader
     * @param string $taskQueue
     */
    public function __construct(ReaderInterface $reader, string $taskQueue)
    {
        $this->reader = $reader;

        $this->bootActivityRepositoryTrait();
    }

    /**
     * TODO
     *
     * {@inheritDoc}
     */
    public function emit(string $body, array $context = []): string
    {
        return '';
    }

    /**
     * @return ReaderInterface
     */
    protected function getReader(): ReaderInterface
    {
        return $this->reader;
    }
}
