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

final class ExecuteChildWorkflow extends Request implements PayloadAwareRequest
{
    protected const CANCELLABLE = true;

    /**
     * @var string
     */
    public const NAME = 'ExecuteChildWorkflow';

    /**
     * @param string $name
     * @param array $args
     * @param array $options
     */
    public function __construct(string $name, array $args, array $options)
    {
        parent::__construct(
            self::NAME,
            [
                'name' => $name,
                'input' => $args,
                'options' => $options,
            ]
        );
    }

    /**
     * {@inheritDoc}
     */
    public function getMappedParams(DataConverterInterface $dataConverter): array
    {
        return \array_merge(
            $this->params,
            [
                'input' => $dataConverter->toPayloads($this->params['input']),
            ]
        );
    }
}
