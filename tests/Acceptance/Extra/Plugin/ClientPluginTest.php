<?php

declare(strict_types=1);

namespace Temporal\Tests\Acceptance\Extra\Plugin\ClientPlugin;

use PHPUnit\Framework\Attributes\Test;
use Temporal\Client\ClientOptions;
use Temporal\Client\WorkflowClient;
use Temporal\Client\WorkflowClientInterface;
use Temporal\Client\WorkflowOptions;
use Temporal\Client\WorkflowStubInterface;
use Temporal\DataConverter\DataConverter;
use Temporal\DataConverter\EncodedValues;
use Temporal\Interceptor\Trait\WorkflowClientCallsInterceptorTrait;
use Temporal\Interceptor\WorkflowClient\StartInput;
use Temporal\Interceptor\WorkflowClientCallsInterceptor;
use Temporal\Plugin\ClientPluginContext;
use Temporal\Plugin\ClientPluginInterface;
use Temporal\Tests\Acceptance\App\Attribute\Stub;
use Temporal\Tests\Acceptance\App\Runtime\Feature;
use Temporal\Tests\Acceptance\App\Runtime\State;
use Temporal\Tests\Acceptance\App\TestCase;
use Temporal\Workflow\WorkflowExecution;
use Temporal\Workflow\WorkflowInterface;
use Temporal\Workflow\WorkflowMethod;

class ClientPluginTest extends TestCase
{
    /**
     * Plugin adds interceptor that modifies workflow arguments.
     */
    #[Test]
    public function pluginInterceptorModifiesArguments(
        WorkflowClientInterface $client,
        Feature $feature,
        State $runtime,
    ): void {
        $pluginClient = WorkflowClient::create(
            serviceClient: $client->getServiceClient(),
            options: (new ClientOptions())->withNamespace($runtime->namespace),
            plugins: [new PrefixPlugin()],
        )->withTimeout(5);

        $stub = $pluginClient->newUntypedWorkflowStub(
            'Extra_Plugin_ClientPlugin',
            WorkflowOptions::new()->withTaskQueue($feature->taskQueue),
        );
        $pluginClient->start($stub, 'hello');

        $result = $stub->getResult('string');
        self::assertSame('plugin:hello', $result);
    }

    /**
     * Multiple plugins apply interceptors in registration order.
     */
    #[Test]
    public function multiplePluginsApplyInOrder(
        WorkflowClientInterface $client,
        Feature $feature,
        State $runtime,
    ): void {
        $pluginClient = WorkflowClient::create(
            serviceClient: $client->getServiceClient(),
            options: (new ClientOptions())->withNamespace($runtime->namespace),
            plugins: [new PrefixPlugin('A:'), new PrefixPlugin2('B:')],
        )->withTimeout(5);

        $stub = $pluginClient->newUntypedWorkflowStub(
            'Extra_Plugin_ClientPlugin',
            WorkflowOptions::new()->withTaskQueue($feature->taskQueue),
        );
        $pluginClient->start($stub, 'test');

        $result = $stub->getResult('string');
        // Plugin interceptors prepend, so A runs first, then B
        self::assertSame('B:A:test', $result);
    }

    /**
     * Duplicate plugin names throw exception.
     */
    #[Test]
    public function duplicatePluginThrowsException(
        WorkflowClientInterface $client,
        State $runtime,
    ): void {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Duplicate plugin "prefix-plugin"');

        WorkflowClient::create(
            serviceClient: $client->getServiceClient(),
            options: (new ClientOptions())->withNamespace($runtime->namespace),
            plugins: [new PrefixPlugin(), new PrefixPlugin()],
        );
    }

    /**
     * Client without plugins works normally.
     */
    #[Test]
    public function noPluginsWorkflow(
        #[Stub('Extra_Plugin_ClientPlugin', args: ['world'])]
        WorkflowStubInterface $stub,
    ): void {
        self::assertSame('world', $stub->getResult('string'));
    }
}


#[WorkflowInterface]
class TestWorkflow
{
    #[WorkflowMethod(name: 'Extra_Plugin_ClientPlugin')]
    public function handle(string $input)
    {
        return $input;
    }
}


class PrefixPlugin implements ClientPluginInterface
{
    public function __construct(
        private readonly string $prefix = 'plugin:',
    ) {}

    public function getName(): string
    {
        return 'prefix-plugin';
    }

    public function configureClient(ClientPluginContext $context): void
    {
        $context->addInterceptor(new PrefixInterceptor($this->prefix));
    }
}


class PrefixPlugin2 implements ClientPluginInterface
{
    public function __construct(
        private readonly string $prefix = 'plugin2:',
    ) {}

    public function getName(): string
    {
        return 'prefix-plugin-2';
    }

    public function configureClient(ClientPluginContext $context): void
    {
        $context->addInterceptor(new PrefixInterceptor($this->prefix));
    }
}


class PrefixInterceptor implements WorkflowClientCallsInterceptor
{
    use WorkflowClientCallsInterceptorTrait;

    public function __construct(
        private readonly string $prefix,
    ) {}

    public function start(StartInput $input, callable $next): WorkflowExecution
    {
        $original = $input->arguments->getValue(0, 'string');

        return $next($input->with(
            arguments: EncodedValues::fromValues([$this->prefix . $original], DataConverter::createDefault()),
        ));
    }
}
