<?php

/**
 * Original proto files:
 * @link https://github.com/temporalio/sdk-core/tree/master/sdk-core-protos/protos/testsrv_upstream/temporal/api/testservice/v1
 */

use \Temporal\Internal\Support\Process;
use Symfony\Component\Process\Exception\ProcessFailedException;

require __DIR__ . '/../../vendor/autoload.php';

echo "Checking prerequisites:\n";

try {
    echo 'protoc: ';
    $plugin = Process::run('which', 'protoc');
    if (trim($plugin) === '') {
        echo "not found\n";
        return;
    }

    echo "{$plugin} [OK]\n";
} catch (ProcessFailedException $e) {
    echo $e->getMessage() . "\n";
    return;
}

try {
    echo 'grpc_php_plugin: ';
    $plugin = Process::run('which', 'grpc_php_plugin');
    if (trim($plugin) === '') {
        echo "not found\n";
        return;
    }

    echo "{$plugin} [OK]\n";
} catch (ProcessFailedException $e) {
    echo $e->getMessage() . "\n";
    return;
}


echo 'api dir: ';
if (is_dir('api')) {
    echo "exists\n";
} else {
    mkdir('api');
    echo "created\n";
}

echo "\nCompiling protobuf client...\n";

chdir(__DIR__ . '/../');

try {
    echo "proto files lookup: ";
    $files = Process::run(
        'find',
        'proto/temporal',
        '-iname',
        '*.proto'
    );

    $files = explode("\n", $files);

    echo "[OK]\n";
} catch (ProcessFailedException $e) {
    echo $e->getMessage() . "\n";
    return;
}

try {
    echo "generating client files: ";
    $result = exec(
        sprintf(
            'protoc --php_out=api/testservice --plugin=protoc-gen-grpc=%s --grpc_out=./api/testservice -Iproto %s',
            $plugin,
            join(' ', $files)
        )
    );

    if (trim($result) !== '') {
        throw new Error($result);
    }

    echo "[OK]\n";
} catch (Error $e) {
    echo $e->getMessage() . "\n";
    return;
}

try {
    echo "generating dependencies: ";

    $result = exec(
        sprintf(
            'protoc --php_out=api/testservice --plugin=protoc-gen-grpc=%s --grpc_out=./api/testservice',
            $plugin,
        )
    );

    if (trim($result) !== '') {
        throw new Error($result);
    }

    echo "[OK]\n";
} catch (Error $e) {
    echo $e->getMessage() . "\n";
    return;
}
