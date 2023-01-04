<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Internal\Transport\Request;

use Temporal\DataConverter\HeaderInterface;
use Temporal\DataConverter\ValuesInterface;
use Temporal\Worker\Transport\Command\Request;

final class ExecuteChildWorkflow extends Request
{
    public const NAME = 'ExecuteChildWorkflow';

    /**
     * @param string $name
     * @param ValuesInterface $input
     * @param array $options
     * @param HeaderInterface $header
     */
    public function __construct(string $name, ValuesInterface $input, array $options, HeaderInterface $header)
    {
        parent::__construct(self::NAME, ['name' => $name, 'options' => $options], $input, header: $header);
    }
}
