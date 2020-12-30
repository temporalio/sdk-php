<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Activity;

use Temporal\Internal\Marshaller\Meta\Marshal;
use Temporal\Internal\Marshaller\Meta\MarshalArray;
use Temporal\Worker\Transport\RpcConnectionInterface;

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
     * @var RpcConnectionInterface
     */
    private RpcConnectionInterface $rpc;

    /**
     * @param RpcConnectionInterface $rpc
     */
    public function __construct(RpcConnectionInterface $rpc)
    {
        $this->info = new ActivityInfo();
        $this->rpc = $rpc;
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

    /**
     * @param mixed $details
     * @return mixed
     */
    public function heartbeat($details)
    {
        return $this->rpc->call('temporal.RecordActivityHeartbeat', [
            'TaskToken' => $this->info->taskToken,
            'Details'   => $details,
        ]);
    }
}
