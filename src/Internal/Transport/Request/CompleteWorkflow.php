<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Internal\Transport\Request;

use Temporal\DataConverter\ValuesInterface;
use Temporal\Worker\Transport\Command\Request;

/**
 * @psalm-immutable
 */
final class CompleteWorkflow extends Request
{
    public const NAME = 'CompleteWorkflow';

    /**
     * @param ValuesInterface $values
     * @param \Throwable|null $failure
     */
    public function __construct(ValuesInterface $values, \Throwable $failure = null)
    {
        parent::__construct(self::NAME, [], $values);
        $this->setFailure($failure);
    }
}
