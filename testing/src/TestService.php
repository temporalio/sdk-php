<?php

declare(strict_types=1);

namespace Temporal\Testing;

use Carbon\Carbon;
use Google\Protobuf\Duration;
use Google\Protobuf\GPBEmpty;
use Google\Protobuf\Timestamp;
use Grpc\ChannelCredentials;
use Temporal\Api\Testservice\V1\GetCurrentTimeResponse;
use Temporal\Api\Testservice\V1\LockTimeSkippingRequest;
use Temporal\Api\Testservice\V1\SleepRequest;
use Temporal\Api\Testservice\V1\SleepUntilRequest;
use Temporal\Api\Testservice\V1\TestServiceClient;
use Temporal\Api\Testservice\V1\UnlockTimeSkippingRequest;
use Temporal\Exception\Client\ServiceClientException;

final class TestService
{
    private TestServiceClient $testServiceClient;

    public function __construct(TestServiceClient $testServiceClient)
    {
        $this->testServiceClient = $testServiceClient;
    }

    public static function create(string $host): self
    {
        return new self(
            new TestServiceClient($host, ['credentials' => ChannelCredentials::createInsecure()])
        );
    }

    /**
     * Increments Time Locking Counter by one.
     *
     * If Time Locking Counter is positive, time skipping is locked (disabled).
     * When time skipping is disabled, the time in test server is moving normally, with a real time pace.
     * Test Server is typically started with locked time skipping and Time Locking Counter = 1.
     *
     * lockTimeSkipping and unlockTimeSkipping calls are counted.
     */
    public function lockTimeSkipping(): void
    {
        $this->invoke('LockTimeSkipping', new LockTimeSkippingRequest());
    }

    /**
     * Decrements Time Locking Counter by one.
     *
     * If the counter reaches 0, it unlocks time skipping and fast forwards time.
     * LockTimeSkipping and UnlockTimeSkipping calls are counted. Calling UnlockTimeSkipping does not
     * guarantee that time is going to be fast forwarded as another lock can be holding it.
     *
     * Time Locking Counter can't be negative, unbalanced calls to unlockTimeSkipping will lead to a failure.
     */
    public function unlockTimeSkipping(): void
    {
        $this->invoke('UnlockTimeSkipping', new UnlockTimeSkippingRequest());
    }

    /**
     * Decreases time locking counter by one and increases it back.
     * Once the Test Server Time advances by the duration specified in the request.
     *
     * This call returns only when the Test Server Time advances by the specified duration.
     *
     * If it is called when Time Locking Counter is
     *   - more than 1 and no other unlocks are coming in, rpc call will block for the specified duration, time will not be fast forwarded.
     *   - 1, it will lead to fast forwarding of the time by the duration specified in the request and quick return of this rpc call.
     *   - 0 will lead to rpc call failure same way as an unbalanced unlockTimeSkipping.
     */
    public function unlockTimeSkippingWithSleep(int $seconds): void
    {
        $duration = (new Duration())->setSeconds($seconds);
        $request = (new SleepRequest())->setDuration($duration);
        $this->invoke('UnlockTimeSkippingWithSleep', $request);
    }

    /**
     * This call returns only when the Test Server Time advances by the specified duration.
     * This is an EXPERIMENTAL API.
     */
    public function sleep(int $seconds): void
    {
        $duration = (new Duration())->setSeconds($seconds);
        $request = (new SleepRequest())->setDuration($duration);
        $this->invoke('Sleep', $request);
    }

    /**
     * This call returns only when the Test Server Time advances to the specified timestamp.
     * If the current Test Server Time is beyond the specified timestamp, returns immediately.
     * This is an EXPERIMENTAL API.
     */
    public function sleepUntil(int $timestamp): void
    {
        $request = (new SleepUntilRequest())->setTimestamp((new Timestamp())->setSeconds($timestamp));
        $this->invoke('sleepUntil', $request);
    }

    /**
     * GetCurrentTime returns the current Temporal Test Server time
     */
    public function getCurrentTime(): Carbon
    {
        /** @var GetCurrentTimeResponse $result */
        $result = $this->invoke('GetCurrentTime', new GPBEmpty());
        return Carbon::createFromTimestamp($result->getTime()->getSeconds());
    }

    private function invoke(string $method, object $request): object
    {
        $call = $this->testServiceClient->{$method}($request);
        [$result, $status] = $call->wait();

        if ($status->code !== 0) {
            throw new ServiceClientException($status);
        }

        return $result;
    }
}
