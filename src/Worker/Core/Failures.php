<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Worker\Core;

use Temporal\Api\Failure\V1\ApplicationFailureInfo;
use Temporal\Api\Failure\V1\Failure;
use Temporal\DataConverter\DataConverterInterface;
use Temporal\Exception\Failure\FailureConverter;

final class Failures
{
    public static function fromThrowable(\Throwable $e, DataConverterInterface $converter): Failure
    {
        try {
            return FailureConverter::mapExceptionToFailure($e, $converter);
        } catch (\Exception $error) {
            if (!self::isInvalidUtf8($error)) {
                throw $error;
            }

            return new Failure([
                'message' => \mb_scrub($e->getMessage(), 'UTF-8'),
                'source' => FailureConverter::SOURCE,
                'stack_trace' => \mb_scrub($e->getTraceAsString(), 'UTF-8'),
                'application_failure_info' => new ApplicationFailureInfo(['type' => $e::class]),
            ]);
        }
    }

    private static function isInvalidUtf8(\Exception $error): bool
    {
        return $error::class === \Exception::class && \stripos($error->getMessage(), 'utf-8') !== false;
    }
}
