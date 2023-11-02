<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Internal\Workflow;

use JetBrains\PhpStorm\Immutable;
use Temporal\DataConverter\EncodedValues;
use Temporal\DataConverter\ValuesInterface;
use Temporal\Interceptor\Header;
use Temporal\Interceptor\HeaderInterface;
use Temporal\Internal\Marshaller\Meta\Marshal;
use Temporal\Workflow\WorkflowInfo;

#[Immutable]
final class Input
{
    /**
     * @var WorkflowInfo
     * @psalm-readonly
     */
    #[Marshal(name: 'info')]
    #[Immutable]
    public WorkflowInfo $info;

    /**
     * @var ValuesInterface
     * @psalm-readonly
     */
    #[Immutable]
    public ValuesInterface $input;

    /**
     * @psalm-readonly
     */
    #[Immutable]
    public Header $header;

    /**
     * @param WorkflowInfo|null $info
     * @param ValuesInterface|null $args
     */
    public function __construct(WorkflowInfo $info = null, ValuesInterface $args = null, HeaderInterface $header = null)
    {
        $this->info = $info ?? new WorkflowInfo();
        $this->input = $args ?? EncodedValues::empty();
        $this->header = $header ?? Header::empty();
    }
}
