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

final class ExecuteActivity extends Request implements PayloadAwareRequest
{
    protected const CANCELLABLE = true;

    /**
     * @var string
     */
    public const NAME = 'ExecuteActivity';

    /**
     * @param string $name
     * @param array $arguments
     * @param array $options
     */
    public function __construct(string $name, array $arguments, array $options)
    {
        parent::__construct(
            self::NAME,
            [
                'name' => $name,
                'arguments' => $arguments,
                'options' => $options,
            ]
        );
    }

    /**
     * @param DataConverterInterface $dataConverter
     * @return array
     */
    public function getMappedParams(DataConverterInterface $dataConverter): array
    {
        return \array_merge(
            $this->params,
            [
                'arguments' => $dataConverter->toPayloads($this->params['arguments']),
            ]
        );
    }
}
