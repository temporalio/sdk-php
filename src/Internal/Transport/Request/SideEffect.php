<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Internal\Transport\Request;

use Temporal\DataConverter\DataConverterInterface;
use Temporal\Worker\Command\PayloadAwareRequest;
use Temporal\Worker\Command\Request;

final class SideEffect extends Request implements PayloadAwareRequest
{
    /**
     * @var string
     */
    public const NAME = 'SideEffect';

    /**
     * @param mixed $value
     */
    public function __construct($value)
    {
        parent::__construct(self::NAME, [
            'value' => $value,
        ]);
    }

    /**
     * @param DataConverterInterface $dataConverter
     * @return array
     */
    public function getMappedParams(DataConverterInterface $dataConverter): array
    {
        return \array_merge($this->params, [
            'value' => $dataConverter->toPayloads($this->params['value']),
        ]);
    }
}
