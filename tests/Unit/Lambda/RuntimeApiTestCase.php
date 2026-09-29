<?php

declare(strict_types=1);

namespace Temporal\Tests\Unit\Lambda;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use Temporal\Lambda\Invocation;
use Temporal\Lambda\RuntimeApi;
use Temporal\Tests\Unit\AbstractUnit;

#[CoversClass(RuntimeApi::class)]
#[UsesClass(Invocation::class)]
final class RuntimeApiTestCase extends AbstractUnit
{
    private RuntimeApiStub $stub;
    private string $stateDir;
    private RuntimeApi $api;

    public function testNextInvocationReadsTheRequestIdAndDeadline(): void
    {
        $before = (int) (\microtime(true) * 1000);

        $invocation = $this->api->nextInvocation();

        self::assertSame('request-0', $invocation->requestId);
        self::assertGreaterThanOrEqual($before, $invocation->deadlineMs);
    }

    public function testMissingRequestIdHeaderIsRejected(): void
    {
        $this->stub->omitHeaders();

        $this->expectExceptionMessage('Runtime API response is missing Lambda-Runtime-Aws-Request-Id');

        $this->api->nextInvocation();
    }

    public function testAnExhaustedRuntimeApiReportsTheHttpStatus(): void
    {
        $this->api->nextInvocation();

        $this->expectExceptionMessage('Failed to fetch the next invocation: HTTP 500');

        $this->api->nextInvocation();
    }

    public function testResponseIsPostedForTheGivenRequestId(): void
    {
        $this->api->respond('request-7');

        self::assertSame(
            ['/2018-06-01/runtime/invocation/request-7/response'],
            $this->stub->acknowledgements(),
        );
    }

    public function testInitErrorCarriesTheExceptionType(): void
    {
        $this->api->reportInitError(new \LogicException('template is unreadable'));

        self::assertSame(['/2018-06-01/runtime/init/error'], $this->stub->acknowledgements());
        self::assertSame(
            [
                'errorType' => 'LogicException',
                'errorMessage' => 'template is unreadable',
            ],
            \array_intersect_key(
                (array) \json_decode($this->stub->lastBody(), true),
                ['errorType' => null, 'errorMessage' => null],
            ),
        );
    }

    public function testANamespacedExceptionTypeUsesDotsAsSeparators(): void
    {
        $this->api->reportInitError(new \Temporal\Lambda\Exception\ConfigurationException('nope'));

        self::assertSame(
            'Temporal.Lambda.Exception.ConfigurationException',
            ((array) \json_decode($this->stub->lastBody(), true))['errorType'],
        );
    }

    public function testAFailedAcknowledgementReportsTheHttpStatus(): void
    {
        $this->stub->failAcknowledgementsWith(503);

        $this->expectExceptionMessage('Failed to send the invocation response: HTTP 503');

        $this->api->respond('request-0');
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->stateDir = \sys_get_temp_dir() . '/temporal-lambda-api-' . \bin2hex(\random_bytes(6));
        \mkdir($this->stateDir);

        $this->stub = new RuntimeApiStub($this->stateDir, 5_000);
        $this->stub->start();
        $this->api = new RuntimeApi($this->stub->host());
    }

    protected function tearDown(): void
    {
        $this->stub->stop();

        foreach ((array) \glob($this->stateDir . '/{,.}[!.,..]*', \GLOB_BRACE) as $file) {
            @\unlink((string) $file);
        }

        \rmdir($this->stateDir);

        parent::tearDown();
    }
}
