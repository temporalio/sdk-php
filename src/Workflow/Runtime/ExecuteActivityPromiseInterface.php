<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Client\Workflow\Runtime;

use React\Promise\PromiseInterface;

interface ExecuteActivityPromiseInterface extends PromiseInterface
{
    /**
     * @param string $argument
     * @param mixed $value
     * @return $this
     */
    public function with(string $argument, $value): self;

    /**
     * @param string $name
     * @param mixed $value
     * @return $this
     */
    public function withOption(string $name, $value): self;
}
