<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Tests\Testing;

use Temporal\Worker\Command\SuccessResponseInterface;

/**
 * @template-extends TestingCommand<SuccessResponseInterface>
 */
class TestingSuccessResponse extends TestingCommand implements SuccessResponseInterface
{
    /**
     * @param SuccessResponseInterface $response
     */
    public function __construct(SuccessResponseInterface $response)
    {
        parent::__construct($response);
    }

    /**
     * {@inheritDoc}
     */
    public function getPayloads(): array
    {
        return $this->command->getPayloads();
    }
}
