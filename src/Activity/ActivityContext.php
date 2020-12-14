<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Client\Activity;

use Temporal\Client\Internal\Marshaller\Meta\Marshal;
use Temporal\Client\Internal\Marshaller\Meta\MarshalArray;

final class ActivityContext implements ActivityContextInterface
{
    /**
     * @var ActivityInfo
     */
    #[Marshal(name: 'info')]
    private ActivityInfo $info;

    /**
     * @var array
     */
    #[MarshalArray(name: 'args')]
    private array $arguments = [];

    /**
     * @var bool
     */
    private bool $doNotCompleteOnReturn = false;

    /**
     * ActivityContext constructor.
     */
    public function __construct()
    {
        $this->info = new ActivityInfo();
    }

    /**
     * {@inheritDoc}
     */
    public function getArguments(): array
    {
        return $this->arguments;
    }

    /**
     * {@inheritDoc}
     */
    public function getInfo(): ActivityInfo
    {
        return $this->info;
    }

    /**
     * {@inheritDoc}
     */
    public function doNotCompleteOnReturn(): void
    {
        $this->doNotCompleteOnReturn = true;
    }

    /**
     * {@inheritDoc}
     */
    public function isDoNotCompleteOnReturn(): bool
    {
        return $this->doNotCompleteOnReturn;
    }
}
