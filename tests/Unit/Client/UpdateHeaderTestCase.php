<?php

declare(strict_types=1);

namespace Temporal\Tests\Unit\Client;

use PHPUnit\Framework\Attributes\DataProvider;
use Temporal\Api\Common\V1\Payload;
use Temporal\Api\Update\V1\UpdateRef;
use Temporal\Api\Workflowservice\V1\ExecuteMultiOperationRequest;
use Temporal\Api\Workflowservice\V1\ExecuteMultiOperationResponse;
use Temporal\Api\Workflowservice\V1\ExecuteMultiOperationResponse\Response;
use Temporal\Api\Workflowservice\V1\StartWorkflowExecutionResponse;
use Temporal\Api\Workflowservice\V1\UpdateWorkflowExecutionRequest;
use Temporal\Api\Workflowservice\V1\UpdateWorkflowExecutionResponse;
use Temporal\Client\GRPC\Context;
use Temporal\Client\GRPC\ServiceClientInterface;
use Temporal\Client\WorkflowClient;
use Temporal\DataConverter\DataConverterInterface;
use Temporal\Interceptor\Header;
use Temporal\Interceptor\SimplePipelineProvider;
use Temporal\Interceptor\WorkflowClient\UpdateInput;
use Temporal\Interceptor\WorkflowClient\UpdateWithStartInput;
use Temporal\Interceptor\WorkflowClientCallsInterceptor;
use Temporal\Tests\Unit\AbstractUnit;

final class UpdateHeaderTestCase extends AbstractUnit
{
    /**
     * @return iterable<string, array{bool, bool}>
     */
    public static function headers(): iterable
    {
        yield 'update, client converter' => [false, false];
        yield 'update with start, client converter' => [true, false];
        yield 'update, explicit converter' => [false, true];
        yield 'update with start, explicit converter' => [true, true];
    }

    #[DataProvider('headers')]
    public function testHeaderConversion(bool $withStart, bool $explicitConverter): void
    {
        $converter = $this->createMock(DataConverterInterface::class);
        $headerConverter = $explicitConverter ? $this->createMock(DataConverterInterface::class) : $converter;
        $encoded = new Payload(['metadata' => ['encoding' => 'custom/header'], 'data' => 'converted']);
        $raw = new Payload(['metadata' => ['encoding' => 'custom/raw'], 'data' => "\x00raw\xff"]);
        $rawBytes = $raw->serializeToString();

        $headerConverter->expects(self::once())->method('toPayload')->with('value')->willReturn($encoded);
        $headerConverter->expects(self::never())->method('fromPayload');
        if ($explicitConverter) {
            $converter->expects(self::never())->method('toPayload');
            $converter->expects(self::never())->method('fromPayload');
        }
        $header = $explicitConverter
            ? Header::fromPayloadCollection(['raw' => $raw], $headerConverter)->withValue('value', 'value')
            : Header::fromValues(['value' => 'value'])->withValue('raw', $raw);

        $interceptor = $this->createMock(WorkflowClientCallsInterceptor::class);
        $interceptor->method('update')->willReturnCallback(
            static fn(UpdateInput $input, callable $next) => $next($input->with(header: $header)),
        );
        $interceptor->method('updateWithStart')->willReturnCallback(
            static fn(UpdateWithStartInput $input, callable $next) => $next(
                $input->with(updateInput: $input->updateInput->with(header: $header)),
            ),
        );

        $service = $this->createMock(ServiceClientInterface::class);
        $service->method('getContext')->willReturn(Context::default());
        $service->method('withContext')->willReturnSelf();
        $response = (new UpdateWorkflowExecutionResponse())->setUpdateRef(
            (new UpdateRef())->setUpdateId('update-id'),
        );
        $checkHeader = static function (UpdateWorkflowExecutionRequest $request) use ($encoded, $rawBytes): void {
            $fields = $request->getRequest()->getInput()->getHeader()->getFields();
            self::assertCount(2, $fields);
            self::assertSame($encoded->serializeToString(), $fields['value']->serializeToString());
            self::assertSame($rawBytes, $fields['raw']->serializeToString());
        };
        if ($withStart) {
            $service->expects(self::once())->method('ExecuteMultiOperation')->willReturnCallback(
                static function (ExecuteMultiOperationRequest $request) use ($checkHeader, $response) {
                    $checkHeader($request->getOperations()[1]->getUpdateWorkflow());

                    return (new ExecuteMultiOperationResponse())->setResponses([
                        (new Response())->setStartWorkflow(
                            (new StartWorkflowExecutionResponse())->setRunId('run-id'),
                        ),
                        (new Response())->setUpdateWorkflow($response),
                    ]);
                },
            );
        } else {
            $service->expects(self::once())->method('UpdateWorkflowExecution')->willReturnCallback(
                static function (UpdateWorkflowExecutionRequest $request) use ($checkHeader, $response) {
                    $checkHeader($request);

                    return $response;
                },
            );
        }
        $client = new WorkflowClient(
            $service,
            converter: $converter,
            interceptorProvider: new SimplePipelineProvider([$interceptor]),
        );

        if ($withStart) {
            $client->updateWithStart($client->newUntypedWorkflowStub('workflow'), 'update');
        } else {
            $client->newUntypedRunningWorkflowStub('workflow-id')->startUpdate('update');
        }

        self::assertSame($headerConverter, $header->getDataConverter());
        self::assertSame($rawBytes, $raw->serializeToString());
    }
}
