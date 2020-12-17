<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Client\Exception;

use Temporal\Client\Internal\Workflow\Process\CancellationScope;
use Temporal\Client\Internal\Workflow\Process\Scope;

class CancellationException extends TemporalException implements NonThrowableExceptionInterface
{
    /**
     * @var string
     */
    private const ERROR_REQUEST = 'Request with id %d has been canceled';

    /**
     * @var string
     */
    private const ERROR_WORKFLOW = 'Workflow %s has been cancelled';

    /**
     * @var string
     */
    private const ERROR_SCOPE = 'Workflow scope %s of workflow %s has been canceled';

    /**
     * @param int $id
     * @return static
     */
    public static function fromRequestId(int $id): self
    {
        return new static(\sprintf(self::ERROR_REQUEST, $id));
    }

    /**
     * @param Scope $scope
     * @return static
     */
    public static function fromScope(Scope $scope): self
    {
        if ($scope instanceof CancellationScope) {
            return new static(\sprintf(self::ERROR_SCOPE, $scope->getId(), $scope->getRunId()));
        }

        return new static(\sprintf(self::ERROR_WORKFLOW, $scope->getId()));
    }
}
