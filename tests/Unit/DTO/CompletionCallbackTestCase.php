<?php

declare(strict_types=1);

namespace Temporal\Tests\Unit\DTO;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Temporal\Internal\Nexus\NexusLinkConverter;
use Temporal\Nexus\Exception\InvalidArgumentException;
use Temporal\Nexus\Link as NexusLink;
use Temporal\Workflow\CompletionCallback;

#[CoversClass(CompletionCallback::class)]
final class CompletionCallbackTestCase extends TestCase
{
    public function testNoLinksByDefault(): void
    {
        $cb = new CompletionCallback('http://x');
        self::assertSame([], $cb->links);
    }

    public function testEmptyUrlRejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new CompletionCallback('');
    }

    public function testWithNexusLinksConvertsAndKeepsOnlyWorkflowEvent(): void
    {
        $uri = 'temporal:///namespaces/n/workflows/w/r/history'
            . '?referenceType=EventReference&eventID=1&eventType=EVENT_TYPE_WORKFLOW_EXECUTION_STARTED';
        $cb = CompletionCallback::withNexusLinks(
            'http://cb',
            ['k' => 'v'],
            [
                new NexusLink($uri, NexusLinkConverter::TYPE_WORKFLOW_EVENT),
                new NexusLink('https://custom/abc', 'custom.type'),
            ],
        );

        self::assertSame('http://cb', $cb->url);
        self::assertSame(['k' => 'v'], $cb->headers);
        self::assertCount(1, $cb->links);
    }

    public function testWithNexusLinksThrowsOnMalformedUri(): void
    {
        $bad = 'https:///namespaces/n/workflows/w/r/history?referenceType=EventReference&eventType=EVENT_TYPE_WORKFLOW_EXECUTION_STARTED';
        $this->expectException(InvalidArgumentException::class);
        CompletionCallback::withNexusLinks('http://cb', [], [new NexusLink($bad, NexusLinkConverter::TYPE_WORKFLOW_EVENT)]);
    }
}
