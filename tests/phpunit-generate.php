#!/usr/bin/env php
<?php

declare(strict_types=1);

$threshold = 1.0;
$junitFiles = [
    __DIR__ . '/../runtime/phpunit-acceptance-junit.xml',
    __DIR__ . '/../runtime/phpunit-acceptance-fast-junit.xml',
    __DIR__ . '/../runtime/phpunit-acceptance-slow-junit.xml',
];
$outputFile = $argv[2] ?? __DIR__ . '/../phpunit.xml.dist';

$junitFiles = \array_filter($junitFiles, 'file_exists');
if (empty($junitFiles)) {
    echo "No junit files found to analyze.\n";
    exit(1);
}

foreach ($argv as $arg) {
    if (\str_starts_with($arg, '--threshold=')) {
        $threshold = (float) \substr($arg, 12);
    }
}

$tests = [];

foreach ($junitFiles as $junitFile) {
    $xml = \simplexml_load_file($junitFile);
    foreach ($xml->xpath('//testcase') as $testcase) {
        $file = (string) $testcase['file'];
        $time = (float) $testcase['time'];

        if (!isset($tests[$file])) {
            $tests[$file] = 0.0;
        }
        $tests[$file] = \max($tests[$file], $time);  // max instead of sum
    }
}

$slow = [];
$fast = [];

foreach ($tests as $file => $time) {
    if ($time >= $threshold) {
        $slow[$file] = $time;
    } else {
        $fast[$file] = $time;
    }
}

\arsort($slow);

$extractPath = static function (string $file): string {
    if (\preg_match('#(tests/Acceptance/.+)$#', $file, $matches)) {
        return $matches[1];
    }
    return $file;
};

$slowFiles = \array_map($extractPath, \array_keys($slow));

$excludeLines = \implode("\n", \array_map(
    static fn(string $file): string => "            <exclude>$file</exclude>",
    $slowFiles,
));

$fileLines = \implode("\n", \array_map(
    static fn(string $file): string => "            <file>$file</file>",
    $slowFiles,
));

$template = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<phpunit xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
         xsi:noNamespaceSchemaLocation="vendor/phpunit/phpunit/phpunit.xsd"
         bootstrap="tests/bootstrap.php"
         cacheResultFile="runtime/.phpunit.result.cache"
         backupGlobals="false"
         colors="true"
         processIsolation="false"
         stopOnFailure="false"
         stopOnError="false"
         stderr="true"
         displayDetailsOnIncompleteTests="true"
         displayDetailsOnSkippedTests="true"
         displayDetailsOnTestsThatTriggerDeprecations="true"
         displayDetailsOnTestsThatTriggerErrors="true"
         displayDetailsOnTestsThatTriggerNotices="true"
         displayDetailsOnTestsThatTriggerWarnings="true">
    <testsuites>
        <testsuite name="Acceptance-Fast">
            <directory suffix="Test.php">tests/Acceptance/Extra</directory>
            <directory suffix="Test.php">tests/Acceptance/Harness</directory>
{{EXCLUDES}}
        </testsuite>
        <testsuite name="Acceptance-Slow">
{{FILES}}
        </testsuite>
        <testsuite name="Acceptance">
            <directory suffix="Test.php">tests/Acceptance/Extra</directory>
            <directory suffix="Test.php">tests/Acceptance/Harness</directory>
        </testsuite>
        <testsuite name="Arch">
            <directory suffix="Test.php">tests/Arch</directory>
        </testsuite>
        <testsuite name="Unit">
            <directory suffix="TestCase.php">tests/Unit</directory>
        </testsuite>
        <testsuite name="Functional">
            <directory suffix="TestCase.php">tests/Functional</directory>
        </testsuite>
    </testsuites>
    <groups>
        <exclude>
            <group>skip-on-test-server</group>
        </exclude>
    </groups>
    <php>
        <ini name="error_reporting" value="-1"/>
        <ini name="memory_limit" value="-1"/>
    </php>
    <source>
        <include>
            <directory>src</directory>
        </include>
    </source>
</phpunit>
XML;

$output = \str_replace(
    ['{{EXCLUDES}}', '{{FILES}}'],
    [$excludeLines, $fileLines],
    $template,
);

\file_put_contents($outputFile, $output);

$slowTime = \array_sum($slow);
$fastTime = \array_sum($fast);

echo "Generated: $outputFile (threshold: {$threshold}s)\n\n";
echo "Fast: " . \count($fast) . " files (~" . \round($fastTime, 1) . "s)\n";
echo "Slow: " . \count($slow) . " files (~" . \round($slowTime, 1) . "s)\n\n";

foreach ($slow as $file => $time) {
    echo \sprintf("%6.2fs  %s\n", $time, $extractPath($file));
}
