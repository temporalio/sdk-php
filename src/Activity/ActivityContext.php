<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Client\Activity;

use Temporal\Client\DataConverter\DataConverterInterface;
use Temporal\Client\DataConverter\Payload;
use Temporal\Client\Internal\Marshaller\Meta\Marshal;
use Temporal\Client\Internal\Marshaller\Meta\MarshalArray;
use Temporal\Client\Worker\Transport\RpcConnectionInterface;

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
    #[MarshalArray(name: 'args', of: Payload::class)]
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
     * @var DataConverterInterface
     */
    private DataConverterInterface $dataConverter;

    /**
     * @param RpcConnectionInterface $rpc
     * @param DataConverterInterface $dataConverter
     */
    public function __construct(RpcConnectionInterface $rpc, DataConverterInterface $dataConverter)
    {
        $this->info = new ActivityInfo();
        $this->rpc = $rpc;
        $this->dataConverter = $dataConverter;
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
     * @return DataConverterInterface
     */
    public function getDataConverter(): DataConverterInterface
    {
        return $this->dataConverter;
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
        return $this->rpc->call(
            'temporal.RecordActivityHeartbeat',
            [
                'TaskToken' => $this->info->taskToken,
                'Details' => $details,
            ]
        );
    }
}
