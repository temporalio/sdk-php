<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Client\Workflow\Info;

use JetBrains\PhpStorm\Pure;

final class WorkflowType
{
    /**
     * @readonly
     * @var string
     */
    public string $name;

    /**
     * @param string $name
     */
    public function __construct(string $name)
    {
        $this->name = $name;
    }

    /**
     * @psalm-param array {Name: string} $data
     *
     * @param array $data
     * @return static
     */
    #[Pure]
    public static function fromArray(array $data): self
    {
        assert(isset($data['Name']) && \is_string($data['Name']));

        return new self($data['Name']);
    }
}
