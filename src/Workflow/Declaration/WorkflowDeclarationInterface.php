<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Client\Workflow\Declaration;

use Temporal\Client\Worker\Declaration\HandledDeclarationInterface;

interface WorkflowDeclarationInterface extends HandledDeclarationInterface
{
    /**
     * @psalm-return iterable<string, callable>
     * @return iterable|callable[]
     */
    public function getQueryHandlers(): iterable;

    /**
     * @param string $name
     * @param callable $callback
     */
    public function addQueryHandler(string $name, callable $callback): void;

    /**
     * @psalm-return iterable<string, callable>
     * @return iterable|callable[]
     */
    public function getSignalHandlers(): iterable;

    /**
     * @param string $name
     * @param callable $callback
     */
    public function addSignalHandler(string $name, callable $callback): void;
}
