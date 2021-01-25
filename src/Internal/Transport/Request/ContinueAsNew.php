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

final class ContinueAsNew extends Request
{
    public const NAME = 'ContinueAsNew';

    /**
     * @param string $name
     * @param ValuesInterface $input
     * @param array $options
     */
    public function __construct(string $name, ValuesInterface $input, array $options)
    {
        parent::__construct(
            self::NAME,
            [
                'name' => $name,
                'options' => $options,
            ],
            $input
        );
    }
}
