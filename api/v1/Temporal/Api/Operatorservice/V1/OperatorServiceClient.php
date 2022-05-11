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
namespace Temporal\Api\Operatorservice\V1;

/**
 * OperatorService API defines how Temporal SDKs and other clients interact with the Temporal server
 * to perform administrative functions like registering a search attribute or a namespace.
 * APIs in this file could be not compatible with Temporal Cloud, hence it's usage in SDKs should be limited by
 * designated APIs that clearly state that they shouldn't be used by the main Application (Workflows & Activities) framework.
 */
class OperatorServiceClient extends \Grpc\BaseStub {

    /**
     * @param string $hostname hostname
     * @param array $opts channel options
     * @param \Grpc\Channel $channel (optional) re-use channel object
     */
    public function __construct($hostname, $opts, $channel = null) {
        parent::__construct($hostname, $opts, $channel);
    }

    /**
     * AddSearchAttributes add custom search attributes.
     *
     * If successful, returns AddSearchAttributesResponse.
     * If fails, returns INTERNAL code with temporal.api.errordetails.v1.SystemWorkflowFailure in Error Details
     * @param \Temporal\Api\Operatorservice\V1\AddSearchAttributesRequest $argument input argument
     * @param array $metadata metadata
     * @param array $options call options
     * @return \Grpc\UnaryCall
     */
    public function AddSearchAttributes(\Temporal\Api\Operatorservice\V1\AddSearchAttributesRequest $argument,
      $metadata = [], $options = []) {
        return $this->_simpleRequest('/temporal.api.operatorservice.v1.OperatorService/AddSearchAttributes',
        $argument,
        ['\Temporal\Api\Operatorservice\V1\AddSearchAttributesResponse', 'decode'],
        $metadata, $options);
    }

    /**
     * RemoveSearchAttributes removes custom search attributes.
     * @param \Temporal\Api\Operatorservice\V1\RemoveSearchAttributesRequest $argument input argument
     * @param array $metadata metadata
     * @param array $options call options
     * @return \Grpc\UnaryCall
     */
    public function RemoveSearchAttributes(\Temporal\Api\Operatorservice\V1\RemoveSearchAttributesRequest $argument,
      $metadata = [], $options = []) {
        return $this->_simpleRequest('/temporal.api.operatorservice.v1.OperatorService/RemoveSearchAttributes',
        $argument,
        ['\Temporal\Api\Operatorservice\V1\RemoveSearchAttributesResponse', 'decode'],
        $metadata, $options);
    }

    /**
     * GetSearchAttributes returns comprehensive information about search attributes.
     * @param \Temporal\Api\Operatorservice\V1\ListSearchAttributesRequest $argument input argument
     * @param array $metadata metadata
     * @param array $options call options
     * @return \Grpc\UnaryCall
     */
    public function ListSearchAttributes(\Temporal\Api\Operatorservice\V1\ListSearchAttributesRequest $argument,
      $metadata = [], $options = []) {
        return $this->_simpleRequest('/temporal.api.operatorservice.v1.OperatorService/ListSearchAttributes',
        $argument,
        ['\Temporal\Api\Operatorservice\V1\ListSearchAttributesResponse', 'decode'],
        $metadata, $options);
    }

}
