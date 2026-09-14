<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Internal\Workflow;

use Psr\Log\LoggerInterface;
use Temporal\Common\PayloadLimitOptions;
use Temporal\DataConverter\DataConverterInterface;
use Temporal\Internal\Support\CommandPayloads;
use Temporal\Worker\Environment\EnvironmentInterface;
use Temporal\Worker\Transport\Command\RequestInterface;

/**
 * Warns when a command produced by a Workflow carries payloads larger than the configured limit.
 *
 * The payloads are measured the same way the server measures them, so the check costs one extra
 * conversion per command that carries payloads; measuring the result of that conversion is free.
 * Replayed commands are skipped entirely: they are not sent to the server, so there is nothing
 * to warn about.
 *
 * @internal
 */
final class PayloadSizeWarner
{
    /**
     * Message code used by all the SDKs for the payload size warning.
     */
    private const MESSAGE_CODE = 'TMPRL1103';

    public function __construct(
        private readonly PayloadLimitOptions $limits,
        private readonly DataConverterInterface $converter,
        private readonly EnvironmentInterface $env,
        private readonly LoggerInterface $logger,
    ) {}

    /**
     * Measure a command that is about to be sent to the server.
     */
    public function check(RequestInterface $request): void
    {
        // A replayed command is not sent anywhere, so it is never measured nor reported,
        // the same way the other SDKs check the payloads only when the request is sent.
        if ($this->env->isReplaying()) {
            return;
        }

        try {
            $sizes = CommandPayloads::sizes(
                $request,
                $this->converter,
                withPayloads: $this->limits->payloadSizeWarning !== null,
                withMemo: $this->limits->memoSizeWarning !== null,
            );

            $this->warn($request->getName(), 'payloads', $sizes['payloads'], $this->limits->payloadSizeWarning);
            $this->warn($request->getName(), 'memo', $sizes['memo'], $this->limits->memoSizeWarning);
        } catch (\Throwable) {
            // Measuring and reporting is an observability feature: it must not affect the Workflow
            // in any way. A value that cannot be converted fails later, in the codec, as before.
        }
    }

    /**
     * @param non-empty-string $kind
     */
    private function warn(string $command, string $kind, int $size, ?int $limit): void
    {
        if ($limit === null || $size <= $limit) {
            return;
        }

        $this->logger->warning(
            \sprintf(
                '[%s] Attempted to upload %s with size that exceeded the warning limit.',
                self::MESSAGE_CODE,
                $kind,
            ),
            ['command' => $command, 'size' => $size, 'limit' => $limit],
        );
    }
}
