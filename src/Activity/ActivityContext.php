<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Activity;

use Temporal\DataConverter\DataConverterInterface;
use Temporal\DataConverter\Type;
use Temporal\DataConverter\ValuesInterface;
use Temporal\Internal\Marshaller\Meta\Marshal;
use Temporal\Worker\Transport\RpcConnectionInterface;

final class ActivityContext implements ActivityContextInterface
{
    #[Marshal(name: 'info')]
    private ActivityInfo $info;

    private bool $doNotCompleteOnReturn = false;
    private RpcConnectionInterface $rpc;
    private DataConverterInterface $converter;
    private ?ValuesInterface $heartbeatDetails = null;

    /**
     * @param RpcConnectionInterface $rpc
     * @param DataConverterInterface $converter
     * @param ValuesInterface|null $lastHeartbeatDetails
     */
    public function __construct(
        RpcConnectionInterface $rpc,
        DataConverterInterface $converter,
        ValuesInterface $lastHeartbeatDetails = null
    ) {
        $this->info = new ActivityInfo();
        $this->rpc = $rpc;
        $this->converter = $converter;
        $this->heartbeatDetails = $lastHeartbeatDetails;
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
    public function getConverter(): DataConverterInterface
    {
        return $this->converter;
    }

    /**
     * @return bool
     */
    public function hasHeartbeatDetails(): bool
    {
        return $this->heartbeatDetails !== null;
    }

    /**
     * @param Type|string $type
     * @return mixed
     */
    public function getHeartbeatDetails($type = null)
    {
        if (!$this->hasHeartbeatDetails()) {
            return null;
        }

        return $this->heartbeatDetails->getValue(0, $type);
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
     */
    public function heartbeat($details): void
    {
        // todo: upgrade
        $this->rpc->call(
            'temporal.RecordActivityHeartbeat',
            [
                'TaskToken' => base64_encode($this->info->taskToken),
                'Details' => $details,
            ]
        );
    }
}
