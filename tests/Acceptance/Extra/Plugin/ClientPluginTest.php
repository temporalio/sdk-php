<?php

declare(strict_types=1);

namespace Temporal\Tests\Acceptance\Extra\Plugin\ClientPlugin;

use http\Client;
use PHPUnit\Framework\Attributes\Test;
use Temporal\Api\Workflowservice\V1\ListNamespacesRequest;
use Temporal\Client\ClientOptions;
use Temporal\Client\GRPC\BaseClient;
use Temporal\Client\GRPC\ContextInterface;
use Temporal\Client\GRPC\ServiceClientInterface;
use Temporal\Client\WorkflowClient;
use Temporal\Client\WorkflowClientInterface;
use Temporal\Client\WorkflowOptions;
use Temporal\Client\WorkflowStubInterface;
use Temporal\DataConverter\DataConverter;
use Temporal\DataConverter\EncodedValues;
use Temporal\Interceptor\GrpcClientInterceptor;
use Temporal\Interceptor\SimplePipelineProvider;
use Temporal\Interceptor\Trait\WorkflowClientCallsInterceptorTrait;
use Temporal\Interceptor\WorkflowClient\StartInput;
use Temporal\Interceptor\WorkflowClientCallsInterceptor;
use Temporal\Plugin\ClientPluginContext;
use Temporal\Plugin\ClientPluginInterface;
use Temporal\Plugin\ConnectionPluginInterface;
use Temporal\Plugin\PluginRegistry;
use Temporal\Tests\Acceptance\App\Attribute\Stub;
use Temporal\Tests\Acceptance\App\Attribute\Worker;
use Temporal\Tests\Acceptance\App\Runtime\Feature;
use Temporal\Tests\Acceptance\App\Runtime\State;
use Temporal\Tests\Acceptance\App\TestCase;
use Temporal\Workflow\WorkflowExecution;
use Temporal\Workflow\WorkflowInterface;
use Temporal\Workflow\WorkflowMethod;

#[Worker(
    plugins: [new PrefixPlugin()],
)]
class ClientPluginTest extends TestCase
{
    /**
     * Plugin from #[Worker(plugins: [...])] is auto-injected into the client.
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
            pluginRegistry: new PluginRegistry([new PrefixPlugin()]),
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
            pluginRegistry: new PluginRegistry([new PrefixPlugin('A:'), new PrefixPlugin2('B:')]),
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
            pluginRegistry: new PluginRegistry([new PrefixPlugin(), new PrefixPlugin()]),
        );
    }

    /**
     * Plugin from #[Worker(plugins: [...])] is also applied via #[Stub] attribute.
     */
    #[Test]
    public function pluginAppliedViaWorkerAttribute(
        #[Stub('Extra_Plugin_ClientPlugin', args: ['world'])]
        WorkflowStubInterface $stub,
    ): void {
        self::assertSame('plugin:world', $stub->getResult('string'));
    }

    /**
     * Connection plugin can set custom metadata on the service client.
     */
    #[Test]
    public function connectionPluginSetsAuthKey(
        WorkflowClientInterface $client,
        State $runtime,
    ): void {
        $key = 'secret-api-key';
        $authPlugin = new AuthPlugin($key);
        $stealer = new CredentialsStealer();

        $workflowClient = WorkflowClient::create(
            serviceClient: $client->getServiceClient(),
            options: (new ClientOptions())->withNamespace($runtime->namespace),
            pluginRegistry: new PluginRegistry([$authPlugin, new class($stealer) implements ConnectionPluginInterface {
                public function __construct(private readonly CredentialsStealer $stealer) {}

                public function configureServiceClient(ServiceClientInterface $serviceClient, callable $next): ServiceClientInterface
                {
                    if ($serviceClient instanceof BaseClient) {
                        $pipeline = new SimplePipelineProvider([$this->stealer]);
                        $serviceClient = $serviceClient->withInterceptorPipeline($pipeline->getPipeline(GrpcClientInterceptor::class));
                    }
                    return $next($serviceClient);
                }

                public function getName(): string
                {
                    return 'test';
                }
            }]),
        );

        $serviceClient = $workflowClient->getServiceClient();
        $serviceClient->ListNamespaces(new ListNamespacesRequest());
        $authKey = $stealer->getAuthKey();

        self::assertSame("Bearer $key", $authKey);
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

    public function configureClient(ClientPluginContext $context, callable $next): void
    {
        $context->addInterceptor(new PrefixInterceptor($this->prefix));
        $next($context);
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

    public function configureClient(ClientPluginContext $context, callable $next): void
    {
        $context->addInterceptor(new PrefixInterceptor($this->prefix));
        $next($context);
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

class AuthPlugin implements ConnectionPluginInterface
{
    public function __construct(
        private readonly string $key,
    ) {}

    public function getName(): string
    {
        return 'auth-plugin';
    }

    public function configureServiceClient(ServiceClientInterface $serviceClient, callable $next): ServiceClientInterface
    {
        return $next($serviceClient->withAuthKey($this->key));
    }
}

class CredentialsStealer implements GrpcClientInterceptor
{
    private ?string $authKey = null;

    public function __construct() {}

    public function getAuthKey(): ?string
    {
        return $this->authKey;
    }

    public function interceptCall(string $method, object $arg, ContextInterface $ctx, callable $next): object
    {
        $this->authKey = $ctx->getMetadata()['Authorization'][0];
        return $next($method, $arg, $ctx);
    }
}
