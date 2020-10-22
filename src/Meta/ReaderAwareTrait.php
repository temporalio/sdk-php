<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Client\Meta;

use Temporal\Client\Meta\Factory as ReaderFactory;

/**
 * @mixin ReaderAwareInterface
 */
trait ReaderAwareTrait
{
    /**
     * @var ReaderInterface|null
     */
    private ?ReaderInterface $reader = null;

    /**
     * @return ReaderInterface
     */
    protected function createReader(): ReaderInterface
    {
        return (new ReaderFactory())->create();
    }

    /**
     * @param ReaderInterface $reader
     * @return $this
     */
    protected function setReader(ReaderInterface $reader): self
    {
        $this->reader = $reader;

        return $this;
    }

    /**
     * @param ReaderInterface $reader
     * @return $this
     */
    public function withReader(ReaderInterface $reader): self
    {
        return (clone $this)->setReader($reader);
    }

    /**
     * @return ReaderInterface
     */
    public function getReader(): ReaderInterface
    {
        if ($this->reader === null) {
            $this->setReader($this->createReader());
        }

        return $this->reader;
    }
}
