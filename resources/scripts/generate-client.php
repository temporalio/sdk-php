<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Grpc\BaseStub;
use Laminas\Code\Generator;
use Laminas\Code\Generator\MethodGenerator;
use Temporal\Api\Operatorservice;
use Temporal\Api\Workflowservice;
use Temporal\Client\Common\ServerCapabilities;
use Temporal\Client\GRPC\Connection\ConnectionInterface;
use Temporal\Client\GRPC\ContextInterface;

require __DIR__ . '/../../vendor/autoload.php';

echo "Compiling clients...\n";

$ctxParam = Generator\ParameterGenerator::fromArray(
    [
        'type' => '?' . ContextInterface::class,
        'name' => 'ctx',
        'defaultValue' => null,
    ],
);
$addressParam = Generator\ParameterGenerator::fromArray(['type' => 'string', 'name' => 'address']);
$optionsParam = Generator\ParameterGenerator::fromArray(['type' => 'array', 'name' => 'options']);

$buildMethodDocBlock = static function (
    ReflectionClass $serviceReflection,
    string $method,
    string $arg,
    string $return,
): string {
    $block = [];

    $original = $serviceReflection->getMethod($method)->getDocComment();
    foreach (\explode("\n", (string) $original) as $line) {
        $line = \trim($line, "\n\r* ");
        if ($line === '/') {
            continue;
        }

        if (\str_starts_with($line, '@')) {
            break;
        }

        $block[] = $line;
    }

    $block[] = '';
    $block[] = \sprintf('@param \\%s $arg', $arg);
    $block[] = '@param ContextInterface|null $ctx';
    $block[] = \sprintf('@return \\%s', $return);
    $block[] = '@throws ServiceClientException';

    return \implode("\n", $block);
};

$baseStubReflection = new ReflectionClass(BaseStub::class);
$buildRpcMap = static function (ReflectionClass $serviceReflection) use ($baseStubReflection): array {
    $methods = [];

    foreach ($serviceReflection->getMethods() as $method) {
        if ($baseStubReflection->hasMethod($method->getName())) {
            continue;
        }

        $request = $method->getParameters()[0]->getType()?->getName();
        $response = $request === null ? null : \substr($request, 0, -7) . 'Response';

        \assert($request !== null);
        \assert(\class_exists($request));
        \assert(\is_string($response) && \class_exists($response));

        $methods[$method->getName()] = [
            'request' => $request,
            'response' => $response,
        ];
    }

    return $methods;
};

$addCommonInterfaceMethods = static function (Generator\InterfaceGenerator $interface) use ($ctxParam): void {
    $method = new MethodGenerator('getContext');
    $method->setReturnType(ContextInterface::class);
    $interface->addMethodFromGenerator($method);

    $method = new MethodGenerator(
        'withContext',
        [Generator\ParameterGenerator::fromArray(['type' => ContextInterface::class, 'name' => 'context'])],
    );
    $method->setReturnType('static');
    $interface->addMethodFromGenerator($method);

    $method = new MethodGenerator(
        'withAuthKey',
        [Generator\ParameterGenerator::fromArray(['type' => '\Stringable|string', 'name' => 'key'])],
    );
    $method->setReturnType('static');
    $interface->addMethodFromGenerator($method);

    $method = new MethodGenerator('getConnection');
    $method->setReturnType(ConnectionInterface::class);
    $interface->addMethodFromGenerator($method);
};

$buildCreateServiceClientMethod = static function (
    string $serviceClientClass,
) use ($addressParam, $optionsParam): MethodGenerator {
    $method = new MethodGenerator(
        'createServiceClient',
        [$addressParam, $optionsParam],
        MethodGenerator::FLAG_PROTECTED | MethodGenerator::FLAG_STATIC,
    );
    $method->setReturnType(BaseStub::class);
    $method->setBody(\sprintf('return new %s($address, $options);', $serviceClientClass));

    return $method;
};

$buildGetServerCapabilitiesInterfaceMethod = static function (): MethodGenerator {
    $method = new MethodGenerator('getServerCapabilities');
    $method->setReturnType('?' . ServerCapabilities::class);

    return $method;
};

$buildGetServerCapabilitiesImplementationMethod = static function (): MethodGenerator {
    $method = new MethodGenerator('getServerCapabilities');
    $method->setReturnType('?' . ServerCapabilities::class);
    $method->setBody(<<<'PHP'
$connection = $this->getInternalConnection();
if ($connection->capabilities !== null) {
    return $connection->capabilities;
}

try {
    $systemInfo = $this->getSystemInfo(new GetSystemInfoRequest());
    $capabilities = $systemInfo->getCapabilities();

    if ($capabilities === null) {
        return null;
    }

    return $connection->capabilities = new ServerCapabilities(
        signalAndQueryHeader: $capabilities->getSignalAndQueryHeader(),
        internalErrorDifferentiation: $capabilities->getInternalErrorDifferentiation(),
        activityFailureIncludeHeartbeat: $capabilities->getActivityFailureIncludeHeartbeat(),
        supportsSchedules: $capabilities->getSupportsSchedules(),
        encodedFailureAttributes: $capabilities->getEncodedFailureAttributes(),
        buildIdBasedVersioning: $capabilities->getBuildIdBasedVersioning(),
        upsertMemo: $capabilities->getUpsertMemo(),
        eagerWorkflowStart: $capabilities->getEagerWorkflowStart(),
        sdkMetadata: $capabilities->getSdkMetadata(),
        countGroupByExecutionStatus: $capabilities->getCountGroupByExecutionStatus(),
        nexus: $capabilities->getNexus(),
    );
} catch (ServiceClientException $e) {
    if ($e->getCode() === StatusCode::UNIMPLEMENTED) {
        return null;
    }

    throw $e;
}
PHP);

    return $method;
};

$buildSetServerCapabilitiesMethod = static function (): MethodGenerator {
    $method = new MethodGenerator(
        'setServerCapabilities',
        [Generator\ParameterGenerator::fromArray(['type' => ServerCapabilities::class, 'name' => 'capabilities'])],
    );
    $method->setBody(<<<'PHP'
\trigger_error(
    'Method ' . __METHOD__ . ' is deprecated and will be removed in the next major release.',
    \E_USER_DEPRECATED,
);
PHP);

    return $method;
};

$clients = [
    [
        'label' => 'workflow',
        'serviceClass' => Workflowservice\V1\WorkflowServiceClient::class,
        'apiNamespacePrefix' => '\Temporal\Api\Workflowservice\\',
        'interfaceName' => 'ServiceClientInterface',
        'implementationName' => 'ServiceClient',
        'interfaceFile' => __DIR__ . '/../../src/Client/GRPC/ServiceClientInterface.php',
        'implementationFile' => __DIR__ . '/../../src/Client/GRPC/ServiceClient.php',
        'uses' => [
            'interface' => [
                'Temporal\Api\Workflowservice\V1',
                'Temporal\Client\Common\ServerCapabilities',
                'Temporal\Exception\Client\ServiceClientException',
            ],
            'implementation' => [
                'Grpc\BaseStub',
                'Temporal\Api\Workflowservice\V1',
                'Temporal\Api\Workflowservice\V1\GetSystemInfoRequest',
                'Temporal\Client\Common\ServerCapabilities',
                'Temporal\Exception\Client\ServiceClientException',
            ],
        ],
        'extraInterfaceMethods' => static fn(): array => [
            $buildGetServerCapabilitiesInterfaceMethod(),
        ],
        'extraImplementationMethods' => static fn(): array => [
            $buildCreateServiceClientMethod('V1\WorkflowServiceClient'),
            $buildGetServerCapabilitiesImplementationMethod(),
            $buildSetServerCapabilitiesMethod(),
        ],
    ],
    [
        'label' => 'operator',
        'serviceClass' => Operatorservice\V1\OperatorServiceClient::class,
        'apiNamespacePrefix' => '\Temporal\Api\Operatorservice\\',
        'interfaceName' => 'OperatorClientInterface',
        'implementationName' => 'OperatorClient',
        'interfaceFile' => __DIR__ . '/../../src/Client/GRPC/OperatorClientInterface.php',
        'implementationFile' => __DIR__ . '/../../src/Client/GRPC/OperatorClient.php',
        'uses' => [
            'interface' => [
                'Temporal\Api\Operatorservice\V1',
                'Temporal\Exception\Client\ServiceClientException',
            ],
            'implementation' => [
                'Grpc\BaseStub',
                'Temporal\Api\Operatorservice\V1',
                'Temporal\Exception\Client\ServiceClientException',
            ],
        ],
        'extraInterfaceMethods' => static fn(): array => [],
        'extraImplementationMethods' => static fn(): array => [
            $buildCreateServiceClientMethod('V1\OperatorServiceClient'),
        ],
    ],
];

foreach ($clients as $client) {
    echo "reading {$client['label']} schema: ";
    $serviceReflection = new ReflectionClass($client['serviceClass']);
    $methods = $buildRpcMap($serviceReflection);
    echo "[OK]\n";

    echo "generating {$client['interfaceName']}: ";
    $interface = new Generator\InterfaceGenerator($client['interfaceName']);
    $addCommonInterfaceMethods($interface);

    foreach (($client['extraInterfaceMethods'])() as $method) {
        $interface->addMethodFromGenerator($method);
    }

    foreach ($methods as $name => $options) {
        $method = new MethodGenerator($name);
        $method->setDocBlock($buildMethodDocBlock($serviceReflection, $name, $options['request'], $options['response']));
        $method->setParameters([
            Generator\ParameterGenerator::fromArray(['type' => $options['request'], 'name' => 'arg']),
            $ctxParam,
        ]);
        $method->setReturnType($options['response']);
        $interface->addMethodFromGenerator($method);
    }

    $method = new MethodGenerator(
        'close',
        [],
        MethodGenerator::FLAG_PUBLIC,
        null,
        'Close the communication channel associated with this stub.',
    );
    $method->setReturnType('void');
    $interface->addMethodFromGenerator($method);
    echo "[OK]\n";

    echo "writing {$client['interfaceName']}: ";
    $file = new Generator\FileGenerator();
    $file->setNamespace('Temporal\\Client\\GRPC');
    $file->setClass($interface);
    $file->setUses($client['uses']['interface']);

    \file_put_contents(
        $client['interfaceFile'],
        \str_replace(
            [$client['apiNamespacePrefix'], '\\Temporal\\Client\\GRPC\\ContextInterface'],
            ['', 'ContextInterface'],
            $file->generate(),
        ),
    );
    echo "[OK]\n";

    echo "generating {$client['implementationName']}: ";
    $implementation = new Generator\ClassGenerator($client['implementationName']);
    $implementation->setExtendedClass('BaseClient');
    $implementation->setImplementedInterfaces([$client['interfaceName']]);

    foreach (($client['extraImplementationMethods'])() as $method) {
        $implementation->addMethodFromGenerator($method);
    }

    foreach ($methods as $name => $options) {
        $method = new MethodGenerator($name);
        $method->setDocBlock($buildMethodDocBlock($serviceReflection, $name, $options['request'], $options['response']));
        $method->setParameters([
            Generator\ParameterGenerator::fromArray(['type' => $options['request'], 'name' => 'arg']),
            $ctxParam,
        ]);
        $method->setReturnType($options['response']);
        $method->setBody(\sprintf('return $this->invoke("%s", $arg, $ctx);', $name));
        $implementation->addMethodFromGenerator($method);
    }
    echo "[OK]\n";

    echo "writing {$client['implementationName']}: ";
    $file = new Generator\FileGenerator();
    $file->setNamespace('Temporal\\Client\\GRPC');
    $file->setClass($implementation);
    $file->setUses($client['uses']['implementation']);

    \file_put_contents(
        $client['implementationFile'],
        \str_replace(
            [$client['apiNamespacePrefix'], '\\Temporal\\Client\\GRPC\\ContextInterface'],
            ['', 'ContextInterface'],
            $file->generate(),
        ),
    );
    echo "[OK]\n";
}
