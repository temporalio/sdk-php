<?php

declare(strict_types=1);

namespace Temporal\Tests\Unit\Client;

use Temporal\Api\Common\V1\Callback;
use Temporal\Client\WorkflowOptions;
use Temporal\Tests\Unit\AbstractUnit;

/**
 * Covers the Nexus-related additions to {@see WorkflowOptions} — `requestId`
 * and `completionCallbacks`. The pre-existing builder methods are exercised
 * indirectly through other suites; this case focuses on the new surface.
 *
 * @group unit
 * @group client
 */
final class WorkflowOptionsTestCase extends AbstractUnit
{
    public function testRequestIdDefaultsToNull(): void
    {
        $options = new WorkflowOptions();

        self::assertNull($options->requestId);
    }

    public function testWithRequestIdReturnsClone(): void
    {
        $original = new WorkflowOptions();
        $updated = $original->withRequestId('req-1');

        self::assertNull($original->requestId, 'original must not be mutated');
        self::assertSame('req-1', $updated->requestId);
        self::assertNotSame($original, $updated);
    }

    public function testWithRequestIdNullClearsOverride(): void
    {
        $options = (new WorkflowOptions())->withRequestId('req-1')->withRequestId(null);

        self::assertNull($options->requestId);
    }

    public function testCompletionCallbacksDefaultEmpty(): void
    {
        $options = new WorkflowOptions();

        self::assertSame([], $options->completionCallbacks);
    }

    public function testWithNexusCompletionCallbackAccumulates(): void
    {
        $options = (new WorkflowOptions())
            ->withNexusCompletionCallback('https://callback.example/done', ['Nexus-Operation-Token' => 'tok-1'])
            ->withNexusCompletionCallback('https://callback.example/other', []);

        self::assertCount(2, $options->completionCallbacks);
        foreach ($options->completionCallbacks as $cb) {
            self::assertInstanceOf(Callback::class, $cb);
            self::assertNotNull($cb->getNexus());
        }

        $first = $options->completionCallbacks[0]->getNexus();
        self::assertSame('https://callback.example/done', $first->getUrl());

        $headerMap = [];
        foreach ($first->getHeader() as $k => $v) {
            $headerMap[(string) $k] = (string) $v;
        }
        self::assertSame(['Nexus-Operation-Token' => 'tok-1'], $headerMap);
    }

    public function testWithNexusCompletionCallbackKeepsOriginalUntouched(): void
    {
        $original = new WorkflowOptions();
        $updated = $original->withNexusCompletionCallback('https://x', []);

        self::assertSame([], $original->completionCallbacks);
        self::assertCount(1, $updated->completionCallbacks);
    }

    public function testWithCompletionCallbacksReplacesList(): void
    {
        $options = (new WorkflowOptions())
            ->withNexusCompletionCallback('https://a', [])
            ->withCompletionCallbacks([]);

        self::assertSame([], $options->completionCallbacks);
    }
}
