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
 * @internal
 */
final class PayloadSizeWarner
{
    private const MESSAGE_CODE = 'TMPRL1103';

    public function __construct(
        private readonly PayloadLimitOptions $limits,
        private readonly DataConverterInterface $converter,
        private readonly EnvironmentInterface $env,
        private readonly LoggerInterface $logger,
    ) {}

    public function check(RequestInterface $request): void
    {
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
