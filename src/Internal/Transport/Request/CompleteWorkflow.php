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
use Temporal\Worker\Command\ErrorResponse;
use Temporal\Worker\Command\PayloadAwareRequest;
use Temporal\Worker\Command\Request;

final class CompleteWorkflow extends Request implements PayloadAwareRequest
{
    /**
     * @var string
     */
    public const NAME = 'CompleteWorkflow';

    /**
     * @param array $result
     * @param \Throwable|null $error
     */
    public function __construct(array $result, \Throwable $error = null)
    {
        parent::__construct(self::NAME);
        $this->params['result'] = $result;
        $this->params['error'] = $error;
    }

    /**
     * @param DataConverterInterface $dataConverter
     * @return array
     */
    public function getMappedParams(DataConverterInterface $dataConverter): array
    {
        if ($this->params['error'] instanceof \Throwable) {
            return [
                'error' => ErrorResponse::exceptionToArray($this->params['error']),
            ];
        }

        return [
            'result' => $dataConverter->toPayloads($this->params['result']),
        ];
    }
}
