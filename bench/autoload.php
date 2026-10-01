<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

const BENCH_DEFAULT_ADDRESS = '127.0.0.1:7557';
const BENCH_DEFAULT_TASK_QUEUE = 'bench';
const BENCH_DEFAULT_ACTIVITY_WORKERS = 4;

foreach (\glob(__DIR__ . '/src/*.php') as $file) {
    require_once $file;
}
