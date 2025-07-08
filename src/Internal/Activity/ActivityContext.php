<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Internal\Activity;

use Temporal\Activity\ActivityCancellationDetails;
use Temporal\Activity\ActivityContextInterface;
use Temporal\Activity\ActivityInfo;
use Temporal\DataConverter\DataConverterInterface;
use Temporal\DataConverter\EncodedValues;
use Temporal\DataConverter\Type;
use Temporal\DataConverter\ValuesInterface;
use Temporal\Exception\Client\ActivityCanceledException;
use Temporal\Exception\Client\ActivityCompletionException;
use Temporal\Exception\Client\ActivityPausedException;
use Temporal\Exception\Client\ServiceClientException;
use Temporal\Interceptor\HeaderInterface;
use Temporal\Internal\Interceptor\HeaderCarrier;
use Temporal\Internal\Marshaller\Meta\Marshal;
use Temporal\Worker\Transport\RPCConnectionInterface;

final class ActivityContext implements ActivityContextInterface, HeaderCarrier
{
    #[Marshal(name: 'info')]
    private ActivityInfo $info;

    private bool $doNotCompleteOnReturn = false;
    private ?\WeakReference $instance = null;
    private ?ActivityCancellationDetails $cancellationDetails = null;

    public function __construct(
        private readonly RPCConnectionInterface $rpc,
        private readonly DataConverterInterface $converter,
        private ValuesInterface $input,
        private HeaderInterface $header,
        private readonly ?ValuesInterface $lastHeartbeatDetails = null,
    ) {
        $this->info = new ActivityInfo();
    }

    public function getInfo(): ActivityInfo
    {
        return $this->info;
    }

    public function getInput(): ValuesInterface
    {
        return $this->input;
    }

    public function getHeader(): HeaderInterface
    {
        return $this->header;
    }

    public function withInput(ValuesInterface $input): self
    {
        $context = clone $this;
        $context->input = $input;

        return $context;
    }

    public function withHeader(HeaderInterface $header): self
    {
        $context = clone $this;
        $context->header = $header;

        return $context;
    }

    public function getDataConverter(): DataConverterInterface
    {
        return $this->converter;
    }

    public function hasHeartbeatDetails(): bool
    {
        return $this->lastHeartbeatDetails !== null;
    }

    /**
     * @param Type|string $type
     * @return mixed
     */
    public function getLastHeartbeatDetails($type = null): mixed
    {
        if (!$this->hasHeartbeatDetails()) {
            return null;
        }

        return $this->lastHeartbeatDetails->getValue(0, $type);
    }

    public function doNotCompleteOnReturn(): void
    {
        $this->doNotCompleteOnReturn = true;
    }

    public function isDoNotCompleteOnReturn(): bool
    {
        return $this->doNotCompleteOnReturn;
    }

    public function heartbeat(mixed $details): void
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
                    'taskToken' => \base64_encode($this->info->taskToken),
                    'details' => \base64_encode($details),
                ],
            );

            $cancelled = (bool) ($response['canceled'] ?? false);
            $paused = (bool) ($response['paused'] ?? false);

            if ($cancelled || $paused) {
                $this->cancellationDetails ??= new ActivityCancellationDetails(
                    cancelRequested: $cancelled,
                    paused: $paused,
                );

                throw $cancelled
                    ? ActivityCanceledException::fromActivityInfo($this->info)
                    : ActivityPausedException::fromActivityInfo($this->info);
            }
        } catch (ServiceClientException $e) {
            throw ActivityCompletionException::fromActivityInfo($this->info, $e);
        }
    }

    public function getCancellationDetails(): ?ActivityCancellationDetails
    {
        return $this->cancellationDetails;
    }

    public function getInstance(): object
    {
        \assert($this->instance !== null, 'Activity instance is not available');
        $activity = $this->instance->get();
        \assert($activity !== null, 'Activity instance is not available');
        return $activity;
    }

    /**
     * Set activity instance.
     *
     * @param object $instance Activity instance.
     * @return $this
     * @internal
     */
    public function withInstance(object $instance): self
    {
        $clone = clone $this;
        $clone->instance = \WeakReference::create($instance);
        return $clone;
    }
}
