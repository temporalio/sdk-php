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
use Temporal\Api\Workflowservice;
use Temporal\Client\Common\ServerCapabilities;
use Temporal\Client\GRPC\Connection\ConnectionInterface;
use Temporal\Client\GRPC\ContextInterface;

require __DIR__ . '/../../vendor/autoload.php';

echo "Compiling client...\n";

echo "reading client schema: ";

$r = new ReflectionClass(Workflowservice\V1\WorkflowServiceClient::class);
$rBase = new ReflectionClass(BaseStub::class);

$ctxParam = Generator\ParameterGenerator::fromArray(
    [
        'type' => \Temporal\Client\GRPC\ContextInterface::class,
        'name' => 'ctx',
        'defaultValue' => null,
    ]
);

$methodDocBlock = function (ReflectionClass $r, string $method, string $arg, string $return) {
    $block = [];

    // copy from existing doc block
    $orig = $r->getMethod($method)->getDocComment();
    foreach (explode("\n", $orig) as $line) {
        $line = trim($line, "\n\r* ");
        if ($line === '/') {
            continue;
        }

        if (substr($line, 0, 1) === '@') {
            break;
        }

        $block[] = $line;
    }

    $block[] = '';
    $block[] = sprintf('@param \\%s $arg', $arg);
    $block[] = sprintf('@param ContextInterface|null $ctx');
    $block[] = sprintf('@return \\%s', $return);
    $block[] = sprintf('@throws ServiceClientException');

    return join("\n", $block);
};

$methods = [];

// fetching available methods

foreach ($r->getMethods() as $m) {
    if ($rBase->hasMethod($m->getName())) {
        continue;
    }

    $method = [
        'request' => null,
        'response' => null,
    ];

    // simple heuristics
    $method['request'] = $m->getParameters()[0]->getType()->getName();
    $method['response'] = substr($method['request'], 0, -7) . 'Response';

    assert(class_exists($method['request']));
    assert(class_exists($method['response']));

    $methods[$m->getName()] = $method;
}

echo "[OK]\n";

echo "generating interface: ";

$interface = new Generator\InterfaceGenerator('ServiceClientInterface');


// getContext(): ContextInterface
$m = new MethodGenerator(
    'getContext',
    [],
    MethodGenerator::FLAG_PUBLIC,
);
$m->setReturnType(ContextInterface::class);
$interface->addMethodFromGenerator($m);
// withContext(ContextInterface $context): static
$m = new MethodGenerator(
    'withContext',
    [Generator\ParameterGenerator::fromArray(['type' => ContextInterface::class, 'name' => 'context'])],
    MethodGenerator::FLAG_PUBLIC,
);
$m->setReturnType('static');
$interface->addMethodFromGenerator($m);
// withAuthKey(string $key): static
$m = new MethodGenerator(
    'withAuthKey',
    [Generator\ParameterGenerator::fromArray(['type' => '\Stringable|string', 'name' => 'key'])],
    MethodGenerator::FLAG_PUBLIC,
);
$m->setReturnType('static');
$interface->addMethodFromGenerator($m);
// public function getConnection(): ConnectionInterface
$m = new MethodGenerator(
    'getConnection',
    [],
    MethodGenerator::FLAG_PUBLIC,
);
$m->setReturnType(ConnectionInterface::class);
$interface->addMethodFromGenerator($m);
// Add Capability methods
$m = new MethodGenerator(
    'getServerCapabilities',
    [],
    MethodGenerator::FLAG_PUBLIC,
);
$m->setReturnType('?' . ServerCapabilities::class);
$interface->addMethodFromGenerator($m);

foreach ($methods as $method => $options) {
    $m = new MethodGenerator($method);

    $m->setDocBlock(($methodDocBlock)($r, $method, $options['request'], $options['response']));
    $m->setParameters(
        [
            Generator\ParameterGenerator::fromArray(['type' => $options['request'], 'name' => 'arg']),
            $ctxParam
        ]
    );
    $m->setReturnType($options['response']);

    $interface->addMethodFromGenerator($m);
}

$m = new MethodGenerator(
    'close',
    [],
    MethodGenerator::FLAG_PUBLIC,
    null,
    'Close the communication channel associated with this stub.'
);
$m->setReturnType('void');
$interface->addMethodFromGenerator($m);

echo "[OK]\n";
echo "writing interface: ";

$file = new Generator\FileGenerator();
$file->setNamespace('Temporal\\Client\\GRPC');
$file->setClass($interface);
$file->setUses(
    [
        'Temporal\Api\Workflowservice\V1',
        'Temporal\Exception\Client\ServiceClientException',
    ]
);

// write and shorten names
file_put_contents(
    __DIR__ . '/../../src/Client/GRPC/ServiceClientInterface.php',
    str_replace(
        ['\\Temporal\\Api\\Workflowservice\\', '\\Temporal\\Client\\GRPC\\ContextInterface'],
        ['', 'ContextInterface'],
        $file->generate()
    )
);
echo "[OK]\n";


echo "generating implementation: ";

$impl = new Generator\ClassGenerator('ServiceClient');
$impl->setExtendedClass('BaseClient');

foreach ($methods as $method => $options) {
    $m = new MethodGenerator($method);

    $m->setDocBlock(($methodDocBlock)($r, $method, $options['request'], $options['response']));
    $m->setParameters(
        [
            Generator\ParameterGenerator::fromArray(['type' => $options['request'], 'name' => 'arg']),
            $ctxParam
        ]
    );
    $m->setReturnType($options['response']);

    $m->setBody(sprintf('return $this->invoke("%s", $arg, $ctx);', $m->getName()));

    $impl->addMethodFromGenerator($m);
}

echo "[OK]\n";

echo "writing implementation: ";

$file = new Generator\FileGenerator();
$file->setNamespace('Temporal\\Client\\GRPC');
$file->setClass($impl);
$file->setUses(
    [
        'Temporal\Api\Workflowservice\V1',
        'Temporal\Exception\Client\ServiceClientException',
    ]
);

// write and shorten names
file_put_contents(
    __DIR__ . '/../../src/Client/GRPC/ServiceClient.php',
    str_replace(
        ['\\Temporal\\Api\\Workflowservice\\', '\\Temporal\\Client\\GRPC\\ContextInterface'],
        ['', 'ContextInterface'],
        $file->generate()
    )
);
echo "[OK]\n";
