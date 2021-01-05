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

final class ContinueAsNew extends Request implements PayloadAwareRequest
{
    /**
     * @var string
     */
    public const NAME = 'ContinueAsNew';

    /**
     * @param string $name
     * @param array $input
     */
    public function __construct(string $name, array $input)
    {
        parent::__construct(
            self::NAME,
            [
                'name' => $name,
                'input' => $input,
            ]
        );
    }

    public function getMappedParams(DataConverterInterface $dataConverter): array
    {
        return [
            'name' => $this->params['name'],
            'input' => $dataConverter->toPayloads($this->params['input'])
        ];
    }
}
