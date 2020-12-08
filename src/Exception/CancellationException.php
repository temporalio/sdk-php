<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Client\Exception;

use Temporal\Client\Internal\Workflow\Process\Scope;

class CancellationException extends TemporalException implements NonThrowableExceptionInterface
{
    /**
     * @param int $id
     * @return static
     */
    public static function fromRequestId(int $id): self
    {
        return new static("Request with id ${id} was canceled");
    }

    /**
     * @param Scope $scope
     * @return static
     */
    public static function fromScope(Scope $scope): self
    {
        return new static("Scope has been cancelled");
    }
}
