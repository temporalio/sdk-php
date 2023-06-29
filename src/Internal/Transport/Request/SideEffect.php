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
use Temporal\Interceptor\HeaderInterface;
use Temporal\Worker\Transport\Command\Request;

final class SideEffect extends Request
{
    public const NAME = 'SideEffect';

    /**
     * @param ValuesInterface $values
     */
    public function __construct(ValuesInterface $values, HeaderInterface $header)
    {
        parent::__construct(name: self::NAME, payloads: $values, header: $header);
    }
}
