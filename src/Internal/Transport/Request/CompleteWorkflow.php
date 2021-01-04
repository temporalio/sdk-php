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

final class CompleteWorkflow extends Request implements PayloadAwareRequest
{
    /**
     * @var string
     */
    public const NAME = 'CompleteWorkflow';

    /**
     * @param mixed $result
     */
    public function __construct($result)
    {
        parent::__construct(self::NAME, [
            'result' => [$result],
        ]);
    }

    /**
     * @param DataConverterInterface $dataConverter
     * @return array
     */
    public function getMappedParams(DataConverterInterface $dataConverter): array
    {
        return [
            'result' => $dataConverter->toPayloads($this->params['result'])
        ];
    }
}
