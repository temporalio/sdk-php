<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Internal\Activity;

use Temporal\Activity\ActivityContextInterface;
use Temporal\Activity\ActivityInfo;
use Temporal\DataConverter\DataConverterInterface;
use Temporal\DataConverter\EncodedValues;
use Temporal\DataConverter\Type;
use Temporal\DataConverter\ValuesInterface;
use Temporal\Exception\Client\ActivityCanceledException;
use Temporal\Exception\Client\ActivityCompletionException;
use Temporal\Exception\Client\ServiceClientException;
use Temporal\Internal\Marshaller\Meta\Marshal;
use Temporal\Worker\Transport\RPCConnectionInterface;

final class ActivityContext implements ActivityContextInterface
{
    #[Marshal(name: 'info')]
    private ActivityInfo $info;

    private bool $doNotCompleteOnReturn = false;
    private RPCConnectionInterface $rpc;
    private DataConverterInterface $converter;
    private ?ValuesInterface $heartbeatDetails;
    private ValuesInterface $input;

    /**
     * @param RPCConnectionInterface $rpc
     * @param DataConverterInterface $converter
     * @param ValuesInterface $input
     * @param ValuesInterface|null $lastHeartbeatDetails
     */
    public function __construct(
        RPCConnectionInterface $rpc,
        DataConverterInterface $converter,
        ValuesInterface $input,
        ValuesInterface $lastHeartbeatDetails = null
    ) {
        $this->info = new ActivityInfo();
        $this->rpc = $rpc;
        $this->converter = $converter;
        $this->heartbeatDetails = $lastHeartbeatDetails;
        $this->input = $input;
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
    public function getInput(): ValuesInterface
    {
        return $this->input;
    }

    /**
     * @return DataConverterInterface
     */
    public function getDataConverter(): DataConverterInterface
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
     * @return bool
     */
    public function isDoNotCompleteOnReturn(): bool
    {
        return $this->doNotCompleteOnReturn;
    }

    /**
     * @param mixed $details
     *
     * @throws ActivityCompletionException
     * @throws ActivityCanceledException
     */
    public function heartbeat($details): void
    {
        // we use native host process RPC here to avoid excessive GRPC connections and to handle throttling
        // on Golang end

        $details = EncodedValues::fromValues([$details], $this->converter)
            ->toPayloads()
            ->serializeToString();

        try {
            $response = $this->rpc->call(
                'temporal.RecordActivityHeartbeat',
                [
                    'taskToken' => base64_encode($this->info->taskToken),
                    'details' => base64_encode($details),
                ]
            );

            if (!empty($response['canceled'])) {
                throw ActivityCanceledException::fromActivityInfo($this->info);
            }
        } catch (ServiceClientException $e) {
            throw ActivityCompletionException::fromActivityInfo($this->info, $e);
        }
    }
}
