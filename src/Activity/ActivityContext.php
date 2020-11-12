<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Client\Activity;

final class ActivityContext implements ActivityContextInterface
{
    /**
     * @var string
     */
    private const KEY_ARGUMENTS = 'args';

    /**
     * @var string
     */
    private const KEY_INFO = 'info';

    /**
     * @var ActivityInfo
     */
    private ActivityInfo $info;

    /**
     * @var array
     */
    private $arguments;

    /** @var bool */
    private $doNotCompleteOnReturn = false;

    /**
     * @param array $params
     * @throws \Exception
     */
    public function __construct(array $params)
    {
        $this->info = ActivityInfo::fromArray($params[self::KEY_INFO]);
        $this->arguments = $params[self::KEY_ARGUMENTS] ?? [];
    }

    /**
     * @return array
     */
    public function getArguments(): array
    {
        return $this->arguments;
    }

    /**
     * @return ActivityInfo
     */
    public function getInfo(): ActivityInfo
    {
        return $this->info;
    }

    /**
     * Call given method to enable external activity completion using activity ID or task token.
     */
    public function doNotCompleteOnReturn(): void
    {
        $this->doNotCompleteOnReturn = true;
    }

    /**
     * @return bool
     * @internal
     */
    public function isDoNotCompleteOnReturn(): bool
    {
        return $this->doNotCompleteOnReturn;
    }
}
