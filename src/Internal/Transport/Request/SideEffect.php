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
use Temporal\Worker\Transport\Command\Client\Request;

final class SideEffect extends Request
{
    public const NAME = 'SideEffect';

    public function __construct(ValuesInterface $values, array $options)
    {
        parent::__construct(self::NAME, ['options' => $options], $values);
    }
}
