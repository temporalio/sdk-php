<?php

declare(strict_types=1);

namespace Temporal\Tests\Unit\Nexus;

use PHPUnit\Framework\Assert;
use React\Promise\Deferred;
use Temporal\DataConverter\ValuesInterface;
use Temporal\Worker\Transport\Command\Client\CommandResponse;

/**
 * Synchronous settle helpers for the already-settled promises produced by Nexus routes.
 */
trait AwaitsNexusPromise
{
    private function await(Deferred $deferred): mixed
    {
        $result = null;
        $error = null;
        $settled = false;

        $deferred->promise()->then(
            static function (mixed $value) use (&$result, &$settled): void {
                $result = $value;
                $settled = true;
            },
            static function (\Throwable $e) use (&$error): void {
                $error = $e;
            },
        );

        if ($error !== null) {
            throw $error;
        }

        Assert::assertTrue($settled, 'promise should resolve');
        return $result;
    }

    private function assertResolved(Deferred $deferred): void
    {
        $this->await($deferred);
    }

    private function awaitReply(Deferred $deferred): CommandResponse
    {
        $result = $this->await($deferred);
        Assert::assertInstanceOf(CommandResponse::class, $result);
        return $result;
    }

    private function awaitCancelResult(Deferred $deferred): ValuesInterface
    {
        $result = $this->await($deferred);
        Assert::assertInstanceOf(ValuesInterface::class, $result);
        return $result;
    }
}
