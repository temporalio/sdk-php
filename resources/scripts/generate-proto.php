<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
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

chdir(__DIR__ . '/../../');

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
            'protoc --php_out=api/v1 --plugin=protoc-gen-grpc=%s --grpc_out=./api/v1 -Iproto %s',
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

$gogo = file_get_contents('proto/dependencies/gogoproto/gogo.proto');

try {
    echo "generating dependencies: ";

    // PHP does not support Syntax2
    file_put_contents(
        'proto/dependencies/gogoproto/gogo.proto',
        str_replace('syntax = "proto2";', 'syntax = "proto3";', $gogo)
    );

    $result = exec(
        sprintf(
            'protoc --php_out=api/v1 --plugin=protoc-gen-grpc=%s --grpc_out=./api/v1 -Iproto %s',
            $plugin,
            'proto/dependencies/gogoproto/gogo.proto'
        )
    );

    if (trim($result) !== '') {
        throw new Error($result);
    }

    echo "[OK]\n";
} catch (Error $e) {
    echo $e->getMessage() . "\n";
    return;
} finally {
    // restoring original file
    file_put_contents('proto/dependencies/gogoproto/gogo.proto', $gogo);
}


copy('resources/protocol.proto', 'proto/protocol.proto');

try {
    echo "generating roadrunner prototol (php): ";

    $result = exec(
        sprintf(
            'protoc --php_out=api/v1 --plugin=protoc-gen-grpc=%s --grpc_out=./api/v1 -Iproto %s',
            $plugin,
            'proto/protocol.proto'
        )
    );

    if (trim($result) !== '') {
        throw new Error($result);
    }

    echo "[OK]\n";
} catch (Error $e) {
    echo $e->getMessage() . "\n";
    return;
} finally {
    // restoring original file
    unlink('proto/protocol.proto');
}
