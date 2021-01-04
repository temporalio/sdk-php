<?php

namespace Temporal\Worker\Command;

use Temporal\DataConverter\DataConverterInterface;

interface PayloadAwareRequest extends RequestInterface
{
    /**
     * @param DataConverterInterface $dataConverter
     * @return array
     */
    public function getMappedParams(DataConverterInterface $dataConverter): array;
}
