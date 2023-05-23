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
use Temporal\Worker\Transport\Command\RequestInterface;

/**
 * @psalm-import-type RequestOptions from RequestInterface
 * @psalm-immutable
 */
final class ContinueAsNew extends Request
{
    public const NAME = 'ContinueAsNew';

    /** @var non-empty-string */
    private string $workflowType;

    /**
     * @param non-empty-string $name
     * @param ValuesInterface $input
     * @param RequestOptions $options
     */
    public function __construct(string $name, ValuesInterface $input, array $options, HeaderInterface $header)
    {
        $this->workflowType = $name;
        parent::__construct(
            self::NAME,
            [
                'name' => $name,
                'options' => $options,
            ],
            $input,
            header: $header,
        );
    }

    /**
     * @return non-empty-string
     */
    public function getWorkflowType(): string
    {
        return $this->workflowType;
    }
}
