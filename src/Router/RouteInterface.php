<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Client\Router;

use Temporal\Client\Transport\Request\InputRequestInterface;

interface RouteInterface
{
    /**
     * @return string
     */
    public function getName(): string;

    /**
     * @return string
     */
    public function getRequest(): string;

    /**
     * @param InputRequestInterface $request
     * @return mixed
     */
    public function handle(InputRequestInterface $request);
}
