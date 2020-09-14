<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Client\Transport\Request;

use Fun\Symbol\Symbol;
use Temporal\Client\Common\Arrayable;

class InputRequest extends Request implements InputRequestInterface
{
    /**
     * @var resource
     */
    private static $label;

    /**
     * @param string $key
     * @param callable $type
     * @return bool
     */
    public function match(string $key, callable $type): bool
    {
        try {
            $data = $this->getOrFail($key);
        } catch (\Throwable $e) {
            return false;
        }

        return $type($data);
    }

    /**
     * @param string $key
     * @return mixed
     */
    private function getOrFail(string $key)
    {
        self::$label ??= Symbol::create();

        // Dirty optimisation hack for checking the field existence, because
        // response can not contain symbols.
        $data = $this->get($key, self::$label);

        if ($data === self::$label) {
            $message = \sprintf('Required field "%s" is missing in the server response', $key);

            throw new \DomainException($message);
        }

        return $data;
    }

    /**
     * @param string $key
     * @param callable $type
     */
    public function matchOrFail(string $key, callable $type): void
    {
        $data = $this->getOrFail($key);

        if (! $type($data)) {
            throw new \DomainException(\sprintf('Field "%s" contains incorrect content', $key));
        }
    }

    /**
     * @param string ...$keys
     * @return bool
     */
    public function has(string ...$keys): bool
    {
        return Arrayable::has($this->payload, ...$keys);
    }

    /**
     * @param string $key
     * @param mixed|null $default
     * @return mixed|null
     */
    public function get(string $key, $default = null)
    {
        return Arrayable::get($this->payload, $key, $default);
    }
}
