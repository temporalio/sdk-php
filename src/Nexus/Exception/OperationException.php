<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Nexus\Exception;

use Temporal\Nexus\OperationState;

/**
 * An operation has failed or was canceled.
 */
final class OperationException extends NexusException
{
    private function __construct(
        public readonly OperationState $state,
        string $message,
        ?\Throwable $cause = null,
    ) {
        parent::__construct($message, 0, $cause);
    }

    public static function failed(string $message, ?\Throwable $cause = null): self
    {
        return new self(OperationState::Failed, $message, $cause);
    }

    public static function failedFromCause(\Throwable $cause): self
    {
        return new self(OperationState::Failed, self::messageFromCause($cause, 'operation failed'), $cause);
    }

    public static function canceled(string $message, ?\Throwable $cause = null): self
    {
        return new self(OperationState::Canceled, $message, $cause);
    }

    public static function canceledFromCause(\Throwable $cause): self
    {
        return new self(OperationState::Canceled, self::messageFromCause($cause, 'operation canceled'), $cause);
    }

    private static function messageFromCause(\Throwable $cause, string $fallback): string
    {
        return $cause->getMessage() !== '' ? $cause->getMessage() : $fallback;
    }
}
