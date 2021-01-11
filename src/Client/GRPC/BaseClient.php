<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Temporal\Client\GRPC;

use Grpc\UnaryCall;
use Temporal\Api\Workflowservice\V1\WorkflowServiceClient;
use Temporal\Exception\ServiceClientException;

abstract class BaseClient implements ServiceClientInterface
{
    /**
     * @var WorkflowServiceClient
     */
    private WorkflowServiceClient $workflowService;

    /**
     * @param WorkflowServiceClient $workflowService
     */
    public function __construct(WorkflowServiceClient $workflowService)
    {
        $this->workflowService = $workflowService;
    }

    /**
     * Close the communication channel associated with this stub.
     */
    public function close(): void
    {
        $this->workflowService->close();
    }

    /**
     * Close connection and destruct client.
     */
    public function __destruct()
    {
        $this->close();
    }

    /**
     * @param string $method
     * @param object $arg
     * @param ContextInterface|null $ctx
     * @return mixed
     *
     * @throw ClientException
     */
    protected function invoke(string $method, object $arg, ContextInterface $ctx = null)
    {
        $ctx = $ctx ?? Context::default();

        // todo: map context

        /** @var UnaryCall $call */
        $call = $this->workflowService->{$method}($arg);
        [$result, $status] = $call->wait();

         // todo: retry?

        if ($status->code !== 0) {
            throw new ServiceClientException($status);
        }

        return $result;
    }

    /**
     * @param string $address
     * @return ServiceClientInterface
     */
    public static function createInsecure(string $address): ServiceClientInterface
    {
        $client = new WorkflowServiceClient(
            $address,
            ['credentials' => \Grpc\ChannelCredentials::createInsecure()]
        );

        return new static($client);
    }

    /**
     * @param string $address
     * @param string $crt Certificate or cert file in x509 format.
     * @return ServiceClientInterface
     */
    public static function createWithCert(string $address, string $crt): ServiceClientInterface
    {
        if (is_file($crt)) {
            $crt = file_get_contents($crt);
        }

        $client = new WorkflowServiceClient(
            $address,
            ['credentials' => \Grpc\ChannelCredentials::createSsl($crt)]
        );

        return new static($client);
    }
}
