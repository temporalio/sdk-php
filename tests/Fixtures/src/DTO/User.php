<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Tests\DTO;

use Temporal\Internal\Marshaller\Meta\Marshal;

class User
{
    #[Marshal(name: "Name")]
    public string $name;

    #[Marshal(name: "Email")]
    public string $email;

    public static function new(string $name, string $email): User
    {
        $new = new self();
        $new->name = $name;
        $new->email = $email;

        return $new;
    }
}
