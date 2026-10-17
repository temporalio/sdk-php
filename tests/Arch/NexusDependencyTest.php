<?php

declare(strict_types=1);

namespace Temporal\Tests\Arch;

use PHPUnit\Architecture\ArchitectureAsserts;
use PHPUnit\Framework\TestCase;
use Temporal\Nexus\Handler\Internal\HandlerInterface;
use Temporal\Nexus\Handler\Internal\MethodOperationHandler;
use Temporal\Nexus\Handler\Internal\ServiceHandler;
use Temporal\Nexus\Handler\Internal\WorkflowRunStarter;
use Temporal\Nexus\Header;
use Temporal\Nexus\Nexus;
use Temporal\Nexus\NexusOperationContext;
use Temporal\Nexus\WorkflowHandle;

final class NexusDependencyTest extends TestCase
{
    protected array $excludedPaths = [
        'vendor',
        'tests',
    ];

    use ArchitectureAsserts;

    /**
     * Public `Temporal\Nexus\*` types that are allowed to reach into
     * `Temporal\Internal\*` / `Temporal\Client\*`: the `Handler\Internal` dispatch
     * subtree plus the facade/DTO types that bridge to the worker runtime.
     *
     * @var list<class-string>
     */
    private const EXEMPT = [
        HandlerInterface::class,
        MethodOperationHandler::class,
        ServiceHandler::class,
        WorkflowRunStarter::class,
        Nexus::class,
        Header::class,
        NexusOperationContext::class,
        WorkflowHandle::class,
    ];

    public function testPublicNexusNamespaceDoesNotDependOnInternalOrClient(): void
    {
        $violations = [];

        foreach ($this->layer() as $object) {
            if (!\str_starts_with($object->name, 'Temporal\\Nexus\\')) {
                continue;
            }
            if (\in_array($object->name, self::EXEMPT, true)) {
                continue;
            }

            foreach ($object->uses as $use) {
                if (\str_starts_with($use, 'Temporal\\Internal\\') || \str_starts_with($use, 'Temporal\\Client\\')) {
                    $violations[] = "{$object->name} -> {$use}";
                }
            }
        }

        self::assertSame(
            [],
            $violations,
            'Public Temporal\Nexus\* classes must depend only on Common/Exception/Workflow (plus the '
            . 'documented exemptions in self::EXEMPT); they must not reach into Temporal\Internal\* or Temporal\Client\*.',
        );
    }
}
