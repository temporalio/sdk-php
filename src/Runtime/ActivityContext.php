<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Client\Runtime;

use Temporal\Client\Transport\Request\InputRequestInterface;

class ActivityContext implements ActivityContextInterface
{
    /**
     * @var InputRequestInterface
     */
    private InputRequestInterface $request;

    /**
     * @param InputRequestInterface $request
     */
    public function __construct(InputRequestInterface $request)
    {
        $this->request = $request;
    }

    /**
     * @return InputRequestInterface
     */
    public function getRequest(): InputRequestInterface
    {
        return $this->request;
    }
}
