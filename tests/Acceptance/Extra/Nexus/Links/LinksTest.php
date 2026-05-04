<?php

declare(strict_types=1);

namespace Temporal\Tests\Acceptance\Extra\Nexus\Links;

use Temporal\Nexus\Attribute\Operation;
use Temporal\Nexus\Attribute\Service;
use Temporal\Nexus\Handler\OperationContext;
use Temporal\Nexus\Link;
use Temporal\Nexus\Nexus;
use PHPUnit\Framework\Attributes\Test;
use Temporal\Client\WorkflowStubInterface;
use Temporal\Tests\Acceptance\App\Attribute\Stub;
use Temporal\Tests\Acceptance\App\Attribute\Worker;
use Temporal\Tests\Acceptance\App\Runtime\State;
use Temporal\Tests\Acceptance\App\TestCase;
use Temporal\Tests\Acceptance\Extra\Nexus\NexusHelper;
use Temporal\Worker\WorkerOptions;
use Temporal\Workflow\WorkflowInterface;
use Temporal\Workflow\WorkflowMethod;

/**
 * Acceptance test: Links attached to OperationContext by the handler are
 * propagated back to the Nexus caller in the response.
 */
#[Worker(options: [self::class, 'workerOptions'])]
class LinksTest extends TestCase
{
    public static function workerOptions(): WorkerOptions
    {
        return WorkerOptions::new()
            ->withMaxConcurrentActivityExecutionSize(10)
            ->withMaxConcurrentNexusTaskExecutionSize(10)
            ->withMaxConcurrentNexusTaskPollers(2);
    }

    #[Test]
    public function handlerCanAttachSingleLinkAndSeeItBack(
        State $state,
        #[Stub('Extra_Nexus_Links_Bootstrap')]
        WorkflowStubInterface $stub,
    ): void {
        $stub->getResult('string');

        [$code, $resp] = $this->invoke($state, 'attachAndReportSingle', 'session-7');

        self::assertSame(200, $code, "Expected 200, got {$code}. Body: {$resp}");
        // Body carries a deterministic marker produced from links the handler saw
        // via OperationContext::$links->all() right after its own links->add() call.
        self::assertStringContainsString('count=1', $resp);
        self::assertStringContainsString('session-7', $resp);
        self::assertStringContainsString('example.session', $resp);
    }

    #[Test]
    public function handlerCanAttachMultipleLinksAndSeeThemBack(
        State $state,
        #[Stub('Extra_Nexus_Links_Bootstrap2')]
        WorkflowStubInterface $stub,
    ): void {
        $stub->getResult('string');

        [$code, $resp] = $this->invoke($state, 'attachAndReportMany', 'job-99');

        self::assertSame(200, $code, "Expected 200, got {$code}. Body: {$resp}");
        self::assertStringContainsString('count=2', $resp);
        self::assertStringContainsString('primary-job-99', $resp);
        self::assertStringContainsString('audit-job-99', $resp);
    }

    #[Test]
    public function handlerStartsWithNoLinks(
        State $state,
        #[Stub('Extra_Nexus_Links_Bootstrap3')]
        WorkflowStubInterface $stub,
    ): void {
        $stub->getResult('string');

        [$code, $resp] = $this->invoke($state, 'reportNoLinks', 'x');

        self::assertSame(200, $code, "Body: {$resp}");
        self::assertStringContainsString('count=0', $resp);
    }

    /**
     * End-to-end verification of the handler-response-Link wire:
     * handler calls $context->links->add(), sdk-php packs them into
     * `_rr_nexus_links` payload metadata, RoadRunner extracts them and
     * calls nexus.AddHandlerLinks which puts them into the handler ctx;
     * Temporal Go SDK reads them via nexus.HandlerLinks(ctx) and emits
     * `Nexus-Link` response headers back to the caller.
     */
    #[Test]
    public function handlerLinksAppearInNexusLinkResponseHeader(
        State $state,
        #[Stub('Extra_Nexus_Links_Bootstrap4')]
        WorkflowStubInterface $stub,
    ): void {
        $stub->getResult('string');

        $helper = NexusHelper::for($state);
        $endpointId = $helper->setupEndpoint(
            $state->namespace,
            'Temporal\\Tests\\Acceptance\\Extra\\Nexus\\Links',
            'nexus-links-hdr',
        );

        [$code, $body, $headers] = $helper->postOperationFull(
            $endpointId,
            'LinkService',
            'attachAndReportMany',
            'order-42',
        );

        self::assertSame(200, $code, "Body: {$body}");
        self::assertArrayHasKey('nexus-link', $headers, \sprintf(
            'Nexus-Link response header missing; got: [%s]',
            \implode(', ', \array_keys($headers)),
        ));

        // RFC 5988 Link headers — value format `<url>; type="T"`. The two
        // links set by attachAndReportMany must both be present (some
        // clients concatenate with comma, some emit separate values).
        $linkValues = \implode("\n", (array) $headers['nexus-link']);
        self::assertStringContainsString('primary-order-42', $linkValues);
        self::assertStringContainsString('audit-order-42', $linkValues);
        self::assertStringContainsString('example.primary', $linkValues);
        self::assertStringContainsString('example.audit', $linkValues);
    }

    /**
     * Strict parsing (LinkParser): a caller-supplied `Nexus-Link` with a
     * missing required field must be rejected with HTTP 400, matching the
     * Java reference SDK. Previously the sdk-php route silently dropped
     * malformed entries.
     */
    #[Test]
    public function malformedCallerNexusLinkReturns400(
        State $state,
        #[Stub('Extra_Nexus_Links_Bootstrap5')]
        WorkflowStubInterface $stub,
    ): void {
        $stub->getResult('string');

        $helper = NexusHelper::for($state);
        $endpointId = $helper->setupEndpoint(
            $state->namespace,
            'Temporal\\Tests\\Acceptance\\Extra\\Nexus\\Links',
            'nexus-links-bad',
        );

        // A Link header value without the mandatory `type` parameter.
        // RoadRunner parses the raw `Nexus-Link` header, builds options.links,
        // sdk-php's LinkParser then rejects with HandlerException(BadRequest).
        [$code, $body] = $helper->postOperation(
            $endpointId,
            'LinkService',
            'reportNoLinks',
            'x',
            ['Nexus-Link' => '<https://caller.test/orphan>'], // no `type="..."`
        );

        self::assertSame(400, $code, "Expected 400 BadRequest, got {$code}. Body: {$body}");
    }

    /**
     * @return array{int, string}
     */
    private function invoke(State $state, string $op, string $body): array
    {
        $helper = NexusHelper::for($state);
        $endpointId = $helper->setupEndpoint(
            $state->namespace,
            'Temporal\\Tests\\Acceptance\\Extra\\Nexus\\Links',
            'nexus-links',
        );

        return $helper->postOperation($endpointId, 'LinkService', $op, $body);
    }
}

// ── Nexus service ────────────────────────────────────────────────────

#[Service(name: 'LinkService')]
class LinkService
{
    #[Operation]
    public function attachAndReportSingle(string $suffix): string
    {
        $context = Nexus::getCurrentContext();
        $context->links->add(new Link(
            "https://example.test/session/{$suffix}",
            'example.session',
        ));
        return self::reportLinks($context);
    }

    #[Operation]
    public function attachAndReportMany(string $suffix): string
    {
        $context = Nexus::getCurrentContext();
        $context->links->add(
            new Link("https://example.test/primary/primary-{$suffix}", 'example.primary'),
            new Link("https://example.test/audit/audit-{$suffix}", 'example.audit'),
        );
        return self::reportLinks($context);
    }

    #[Operation]
    public function reportNoLinks(string $_ignored): string
    {
        return self::reportLinks(Nexus::getCurrentContext());
    }

    /**
     * Serializes the links currently attached to the given OperationContext into
     * a compact string the test can assert on.
     */
    private static function reportLinks(OperationContext $context): string
    {
        $links = $context->links->all();
        $parts = [];
        foreach ($links as $link) {
            $parts[] = "{$link->uri}|{$link->type}";
        }
        return \sprintf('count=%d;links=[%s]', \count($links), \implode(';', $parts));
    }
}

// ── Bootstrap workflows ──────────────────────────────────────────────

#[WorkflowInterface]
class LinksBootstrapWorkflow
{
    #[WorkflowMethod(name: 'Extra_Nexus_Links_Bootstrap')]
    public function run(): string
    {
        return 'ready';
    }
}

#[WorkflowInterface]
class LinksBootstrapWorkflow2
{
    #[WorkflowMethod(name: 'Extra_Nexus_Links_Bootstrap2')]
    public function run(): string
    {
        return 'ready';
    }
}

#[WorkflowInterface]
class LinksBootstrapWorkflow3
{
    #[WorkflowMethod(name: 'Extra_Nexus_Links_Bootstrap3')]
    public function run(): string
    {
        return 'ready';
    }
}

#[WorkflowInterface]
class LinksBootstrapWorkflow4
{
    #[WorkflowMethod(name: 'Extra_Nexus_Links_Bootstrap4')]
    public function run(): string
    {
        return 'ready';
    }
}

#[WorkflowInterface]
class LinksBootstrapWorkflow5
{
    #[WorkflowMethod(name: 'Extra_Nexus_Links_Bootstrap5')]
    public function run(): string
    {
        return 'ready';
    }
}
