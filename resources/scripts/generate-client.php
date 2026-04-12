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
use Temporal\Client\GRPC\GrpcClientInterface;
use Temporal\Client\Common\ServerCapabilities;
use Temporal\Client\GRPC\ContextInterface;
use Laminas\Code\DeclareStatement;

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

$buildCreateServiceClientMethod = static function (
    string $serviceClientClass,
) use ($addressParam, $optionsParam): MethodGenerator {
    $method = new MethodGenerator(
        'createGrpcStub',
        [$addressParam, $optionsParam],
        MethodGenerator::FLAG_PROTECTED | MethodGenerator::FLAG_STATIC,
    );
    $method->setReturnType('\Grpc\BaseStub');
    $method->setBody(\sprintf('return new %s($address, $options);', $serviceClientClass));

    return $method;
};

$buildGetServerCapabilitiesInterfaceMethod = static function (): MethodGenerator {
    $method = new MethodGenerator('getServerCapabilities');
    $method->setReturnType('?\Temporal\Client\Common\ServerCapabilities');

    return $method;
};

$buildGetServerCapabilitiesImplementationMethod = static function (): MethodGenerator {
    $method = new MethodGenerator('getServerCapabilities');
    $method->setReturnType('?\Temporal\Client\Common\ServerCapabilities');
    $method->setBody(<<<'PHP'
$connection = $this->getInternalConnection();
if ($connection->getCapabilities() !== null) {
    return $connection->getCapabilities();
}

try {
    $systemInfo = $this->getSystemInfo(new GetSystemInfoRequest());
    $capabilities = $systemInfo->getCapabilities();

    if ($capabilities === null) {
        return null;
    }

    $serverCapabilities = new ServerCapabilities(
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
    $connection->setCapabilities($serverCapabilities);

    return $serverCapabilities;
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
        [Generator\ParameterGenerator::fromArray(['type' => '\Temporal\Client\Common\ServerCapabilities', 'name' => 'capabilities'])],
    );
    $method->setReturnType('void');
    $method->setBody(<<<'PHP'
\trigger_error(
    'Method ' . __METHOD__ . ' is deprecated and will be removed in the next major release.',
    \E_USER_DEPRECATED,
);

$this->getInternalConnection()->setCapabilities($capabilities);
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
                'Temporal\Api\Workflowservice\V1',
                'Temporal\Api\Workflowservice\V1\GetSystemInfoRequest',
                'Temporal\Client\Common\ServerCapabilities',
                'Temporal\Exception\Client\ServiceClientException',
            ],
        ],
        'extraInterfaceMethods' => static fn(): array => [$buildGetServerCapabilitiesInterfaceMethod()],
        'extraImplementationMethods' => static fn(): array => [
            $buildGetServerCapabilitiesImplementationMethod(),
            $buildSetServerCapabilitiesMethod(),
            $buildCreateServiceClientMethod('V1\WorkflowServiceClient'),
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
                'Temporal\Api\Operatorservice\V1',
                'Temporal\Exception\Client\ServiceClientException',
            ],
        ],
        'extraInterfaceMethods' => static fn(): array => [],
        'extraImplementationMethods' => static fn(): array => [$buildCreateServiceClientMethod('V1\OperatorServiceClient')],
    ],
];

$generatedFiles = [];

foreach ($clients as $client) {
    echo "reading {$client['label']} schema: ";
    $serviceReflection = new ReflectionClass($client['serviceClass']);
    $methods = $buildRpcMap($serviceReflection);
    echo "[OK]\n";

    echo "generating {$client['interfaceName']}: ";
    $interface = new Generator\InterfaceGenerator($client['interfaceName']);
    $interface->setImplementedInterfaces([GrpcClientInterface::class]);

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

    echo "[OK]\n";

    echo "writing {$client['interfaceName']}: ";
    $file = new Generator\FileGenerator();
    $file->setNamespace('Temporal\\Client\\GRPC');
    $file->setDeclares([DeclareStatement::fromArray(['strict_types' => 1])]);
    $file->setClass($interface);
    $file->setUses($client['uses']['interface']);

    \file_put_contents(
        $client['interfaceFile'],
        \str_replace(
            [$client['apiNamespacePrefix'], '\\Temporal\\Client\\GRPC\\', '\\Temporal\\Client\\Common\\'],
            ['', '', ''],
            $file->generate(),
        ),
    );
    $generatedFiles[] = $client['interfaceFile'];
    echo "[OK]\n";

    echo "generating {$client['implementationName']}: ";
    $implementation = new Generator\ClassGenerator($client['implementationName']);
    $implementation->setExtendedClass('BaseClient');
    $implementation->setImplementedInterfaces([$client['interfaceName']]);

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

    foreach (($client['extraImplementationMethods'])() as $method) {
        $implementation->addMethodFromGenerator($method);
    }
    echo "[OK]\n";

    echo "writing {$client['implementationName']}: ";
    $file = new Generator\FileGenerator();
    $file->setNamespace('Temporal\\Client\\GRPC');
    $file->setDeclares([DeclareStatement::fromArray(['strict_types' => 1])]);
    $file->setClass($implementation);
    $file->setUses($client['uses']['implementation']);

    \file_put_contents(
        $client['implementationFile'],
        \str_replace(
            [$client['apiNamespacePrefix'], '\\Temporal\\Client\\GRPC\\', '\\Temporal\\Client\\Common\\'],
            ['', '', ''],
            $file->generate(),
        ),
    );
    $generatedFiles[] = $client['implementationFile'];
    echo "[OK]\n";
}

echo "formatting generated files: ";
$command = \implode(' ', [
    \escapeshellarg(PHP_BINARY),
    \escapeshellarg(__DIR__ . '/../../vendor/bin/php-cs-fixer'),
    'fix',
    '--config=' . \escapeshellarg(__DIR__ . '/../../.php-cs-fixer.dist.php'),
    '--path-mode=intersection',
    '--using-cache=no',
    '--allow-unsupported-php-version=yes',
    '--sequential',
    ...\array_map(static fn(string $path): string => \escapeshellarg($path), $generatedFiles),
]);

\passthru($command, $exitCode);
$exitCode === 0 or throw new RuntimeException('Failed to format generated files.');
echo "[OK]\n";
