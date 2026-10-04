<?php

declare(strict_types=1);

namespace Temporal\Tests\Core\Client;

use PHPUnit\Framework\TestCase;
use Temporal\Api\Cloud\Cloudservice\V1\GetNamespacesRequest;
use Temporal\Api\Operatorservice\V1\ListSearchAttributesRequest;
use Temporal\Api\Workflowservice\V1\GetSystemInfoRequest;
use Temporal\Client\Common\RpcRetryOptions;
use Temporal\Client\GRPC\BaseClient;
use Temporal\Client\GRPC\CloudClient;
use Temporal\Client\GRPC\Context;
use Temporal\Client\GRPC\OperatorClient;
use Temporal\Client\GRPC\ServiceClient;
use Temporal\Client\GRPC\StatusCode;
use Temporal\Exception\Client\ServiceClientException;
use Temporal\Testing\TestService;

final class ClientTransportTestCase extends TestCase
{
    private const CLOSED_ADDRESS = '127.0.0.1:1';

    public function testServiceClientReportsUnavailableServer(): void
    {
        $client = ServiceClient::create(self::CLOSED_ADDRESS)->withContext(self::singleAttempt());

        $this->expectUnavailable();

        $client->GetSystemInfo(new GetSystemInfoRequest());
    }

    public function testOperatorClientReportsUnavailableServer(): void
    {
        $client = OperatorClient::create(self::CLOSED_ADDRESS)->withContext(self::singleAttempt());

        $this->expectUnavailable();

        $client->ListSearchAttributes(new ListSearchAttributesRequest());
    }

    public function testCloudClientOverTlsReportsUnavailableServer(): void
    {
        $client = CloudClient::createSSL(self::CLOSED_ADDRESS, self::certificate(), overrideServerName: 'localhost')
            ->withContext(self::singleAttempt());

        $this->expectUnavailable();

        $client->GetNamespaces(new GetNamespacesRequest());
    }

    public function testTestServiceReportsUnavailableServer(): void
    {
        $service = TestService::create(self::CLOSED_ADDRESS);

        $this->expectUnavailable();

        $service->lockTimeSkipping();
    }

    public function testClientWithoutCoreStubTellsToInstallGrpc(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage(BaseClient::class . ' has no sdk-core stub, install ext-grpc to use it.');

        (new \ReflectionMethod(BaseClient::class, 'createCoreStub'))->invoke(null, self::CLOSED_ADDRESS, null);
    }

    private function expectUnavailable(): void
    {
        $this->expectException(ServiceClientException::class);
        $this->expectExceptionCode(StatusCode::UNAVAILABLE);
    }

    private static function singleAttempt(): Context
    {
        return Context::default()->withRetryOptions(RpcRetryOptions::new()->withMaximumAttempts(1));
    }

    private static function certificate(): string
    {
        $key = \openssl_pkey_new(['private_key_type' => \OPENSSL_KEYTYPE_EC, 'curve_name' => 'prime256v1']);
        $certificate = \openssl_csr_sign(\openssl_csr_new(['commonName' => 'localhost'], $key), null, $key, 1);
        \openssl_x509_export($certificate, $pem);

        return $pem;
    }
}
