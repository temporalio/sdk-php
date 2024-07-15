<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Worker\Transport\Command\Client;

use Temporal\DataConverter\ValuesInterface;
use Temporal\Worker\Transport\Command\FailureResponseInterface;
use Temporal\Worker\Transport\Command\SuccessResponseInterface;

final class Response
{
    public static function createSuccess(ValuesInterface $values, int|string $id): SuccessResponseInterface
    {
        return new SuccessClientResponse($id, $values);
    }

    public static function createFailure(\Throwable $failure, int|string $id): FailureResponseInterface
    {
        return new FailedClientResponse($id, $failure);
    }
}
