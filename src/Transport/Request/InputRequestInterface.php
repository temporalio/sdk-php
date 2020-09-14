<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Client\Transport\Request;

interface InputRequestInterface extends RequestInterface
{
    /**
     * @param string $key
     * @param callable $type
     */
    public function matchOrFail(string $key, callable $type): void;

    /**
     * @param string $key
     * @param callable $type
     * @return bool
     */
    public function match(string $key, callable $type): bool;

    /**
     * @param string ...$keys
     * @return bool
     */
    public function has(string ...$keys): bool;

    /**
     * @param string $key
     * @param mixed $default
     * @return mixed
     */
    public function get(string $key, $default = null);
}
