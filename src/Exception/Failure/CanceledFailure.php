<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Exception\Failure;

use Temporal\DataConverter\DataConverterInterface;
use Temporal\DataConverter\EncodedValues;
use Temporal\DataConverter\ValuesInterface;

class CanceledFailure extends TemporalFailure
{
    private ValuesInterface $details;

    public function __construct(string $message, ValuesInterface $details = null, \Throwable $previous = null)
    {
        parent::__construct($message, '', $previous);
        $this->details = $details ?? EncodedValues::empty();
    }

    public function getDetails(): ValuesInterface
    {
        return $this->details;
    }

    public function setDataConverter(DataConverterInterface $converter): void
    {
        $this->details->setDataConverter($converter);
    }
}
