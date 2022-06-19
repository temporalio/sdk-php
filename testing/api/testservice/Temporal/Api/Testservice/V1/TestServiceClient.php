<?php
// GENERATED CODE -- DO NOT EDIT!

// Original file comments:
// The MIT License
//
// Copyright (c) 2020 Temporal Technologies Inc.  All rights reserved.
//
// Permission is hereby granted, free of charge, to any person obtaining a copy
// of this software and associated documentation files (the "Software"), to deal
// in the Software without restriction, including without limitation the rights
// to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
// copies of the Software, and to permit persons to whom the Software is
// furnished to do so, subject to the following conditions:
//
// The above copyright notice and this permission notice shall be included in
// all copies or substantial portions of the Software.
//
// THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
// IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
// FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
// AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
// LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
// OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN
// THE SOFTWARE.
//
namespace Temporal\Api\Testservice\V1;

/**
 * TestService API defines an interface supported only by the Temporal Test Server.
 * It provides functionality needed or supported for testing purposes only.
 *
 * This is an EXPERIMENTAL API.
 */
class TestServiceClient extends \Grpc\BaseStub {

    /**
     * @param string $hostname hostname
     * @param array $opts channel options
     * @param \Grpc\Channel $channel (optional) re-use channel object
     */
    public function __construct($hostname, $opts, $channel = null) {
        parent::__construct($hostname, $opts, $channel);
    }

    /**
     * LockTimeSkipping increments Time Locking Counter by one.
     *
     * If Time Locking Counter is positive, time skipping is locked (disabled).
     * When time skipping is disabled, the time in test server is moving normally, with a real time pace.
     * Test Server is typically started with locked time skipping and Time Locking Counter = 1.
     *
     * LockTimeSkipping and UnlockTimeSkipping calls are counted.
     * @param \Temporal\Api\Testservice\V1\LockTimeSkippingRequest $argument input argument
     * @param array $metadata metadata
     * @param array $options call options
     * @return \Grpc\UnaryCall
     */
    public function LockTimeSkipping(\Temporal\Api\Testservice\V1\LockTimeSkippingRequest $argument,
      $metadata = [], $options = []) {
        return $this->_simpleRequest('/temporal.api.testservice.v1.TestService/LockTimeSkipping',
        $argument,
        ['\Temporal\Api\Testservice\V1\LockTimeSkippingResponse', 'decode'],
        $metadata, $options);
    }

    /**
     * UnlockTimeSkipping decrements Time Locking Counter by one.
     *
     * If the counter reaches 0, it unlocks time skipping and fast forwards time.
     * LockTimeSkipping and UnlockTimeSkipping calls are counted. Calling UnlockTimeSkipping does not
     * guarantee that time is going to be fast forwarded as another lock can be holding it.
     *
     * Time Locking Counter can't be negative, unbalanced calls to UnlockTimeSkipping will lead to rpc call failure
     * @param \Temporal\Api\Testservice\V1\UnlockTimeSkippingRequest $argument input argument
     * @param array $metadata metadata
     * @param array $options call options
     * @return \Grpc\UnaryCall
     */
    public function UnlockTimeSkipping(\Temporal\Api\Testservice\V1\UnlockTimeSkippingRequest $argument,
      $metadata = [], $options = []) {
        return $this->_simpleRequest('/temporal.api.testservice.v1.TestService/UnlockTimeSkipping',
        $argument,
        ['\Temporal\Api\Testservice\V1\UnlockTimeSkippingResponse', 'decode'],
        $metadata, $options);
    }

    /**
     * This call returns only when the Test Server Time advances by the specified duration.
     * This is an EXPERIMENTAL API.
     * @param \Temporal\Api\Testservice\V1\SleepRequest $argument input argument
     * @param array $metadata metadata
     * @param array $options call options
     * @return \Grpc\UnaryCall
     */
    public function Sleep(\Temporal\Api\Testservice\V1\SleepRequest $argument,
      $metadata = [], $options = []) {
        return $this->_simpleRequest('/temporal.api.testservice.v1.TestService/Sleep',
        $argument,
        ['\Temporal\Api\Testservice\V1\SleepResponse', 'decode'],
        $metadata, $options);
    }

    /**
     * This call returns only when the Test Server Time advances to the specified timestamp.
     * If the current Test Server Time is beyond the specified timestamp, returns immediately.
     * This is an EXPERIMENTAL API.
     * @param \Temporal\Api\Testservice\V1\SleepUntilRequest $argument input argument
     * @param array $metadata metadata
     * @param array $options call options
     * @return \Grpc\UnaryCall
     */
    public function SleepUntil(\Temporal\Api\Testservice\V1\SleepUntilRequest $argument,
      $metadata = [], $options = []) {
        return $this->_simpleRequest('/temporal.api.testservice.v1.TestService/SleepUntil',
        $argument,
        ['\Temporal\Api\Testservice\V1\SleepResponse', 'decode'],
        $metadata, $options);
    }

    /**
     * UnlockTimeSkippingWhileSleep decreases time locking counter by one and increases it back
     * once the Test Server Time advances by the duration specified in the request.
     *
     * This call returns only when the Test Server Time advances by the specified duration.
     *
     * If it is called when Time Locking Counter is
     *   - more than 1 and no other unlocks are coming in, rpc call will block for the specified duration, time will not be fast forwarded.
     *   - 1, it will lead to fast forwarding of the time by the duration specified in the request and quick return of this rpc call.
     *   - 0 will lead to rpc call failure same way as an unbalanced UnlockTimeSkipping.
     * @param \Temporal\Api\Testservice\V1\SleepRequest $argument input argument
     * @param array $metadata metadata
     * @param array $options call options
     * @return \Grpc\UnaryCall
     */
    public function UnlockTimeSkippingWithSleep(\Temporal\Api\Testservice\V1\SleepRequest $argument,
      $metadata = [], $options = []) {
        return $this->_simpleRequest('/temporal.api.testservice.v1.TestService/UnlockTimeSkippingWithSleep',
        $argument,
        ['\Temporal\Api\Testservice\V1\SleepResponse', 'decode'],
        $metadata, $options);
    }

    /**
     * GetCurrentTime returns the current Temporal Test Server time
     *
     * This time might not be equal to {@link System#currentTimeMillis()} due to time skipping.
     * @param \Google\Protobuf\GPBEmpty $argument input argument
     * @param array $metadata metadata
     * @param array $options call options
     * @return \Grpc\UnaryCall
     */
    public function GetCurrentTime(\Google\Protobuf\GPBEmpty $argument,
      $metadata = [], $options = []) {
        return $this->_simpleRequest('/temporal.api.testservice.v1.TestService/GetCurrentTime',
        $argument,
        ['\Temporal\Api\Testservice\V1\GetCurrentTimeResponse', 'decode'],
        $metadata, $options);
    }

}
