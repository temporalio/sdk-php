<?php

declare(strict_types=1);

namespace Temporal\Tests\Unit\Internal\Transport\Request;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Temporal\Internal\Transport\Request\GetNexusOperationStarted;

#[CoversClass(GetNexusOperationStarted::class)]
final class GetNexusOperationStartedTestCase extends TestCase
{
    public function testNamePinsWireContract(): void
    {
        // Wire contract with RoadRunner — must match the case label in
        // aggregatedpool/handler.go and the registry name in
        // internal/protocol.go.
        self::assertSame('GetNexusOperationStarted', GetNexusOperationStarted::NAME);
    }

    public function testCarriesStartIdInOptions(): void
    {
        $request = new GetNexusOperationStarted(123);

        self::assertSame('GetNexusOperationStarted', $request->getName());
        self::assertSame(['id' => 123], $request->getOptions());
    }
}
