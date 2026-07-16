<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Worker\ActivityInvocationCache;

use React\Promise\PromiseInterface;
use Temporal\Worker\Transport\Command\ServerRequestInterface;

interface ActivityInvocationCacheInterface
{
    public function clear(): void;

    /**
     * @param non-empty-string $activityMethodName
     * @param mixed $value Activity result: anything the DataConverter can encode, or an EncodedValues.
     */
    public function saveCompletion(string $activityMethodName, mixed $value): void;

    /**
     * @param non-empty-string $activityMethodName
     */
    public function saveFailure(string $activityMethodName, \Throwable $error): void;

    /**
     * @param non-empty-string $activityMethodName
     * @param list<mixed> $values Per-call results, each anything the DataConverter can encode.
     */
    public function saveConsecutiveCompletions(string $activityMethodName, array $values): void;

    /**
     * @param non-empty-string $activityMethodName
     * @param list<mixed> $args Call arguments to byte-match against, encoded via the DataConverter.
     * @param mixed $value Result returned on a match: anything the DataConverter can encode, or an EncodedValues.
     */
    public function saveCompletionWhen(string $activityMethodName, array $args, mixed $value): void;

    public function canHandle(ServerRequestInterface $request): bool;

    public function execute(ServerRequestInterface $request): PromiseInterface;
}
