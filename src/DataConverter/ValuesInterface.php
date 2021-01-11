<?php


namespace Temporal\DataConverter;


interface ValuesInterface
{
    /**
     * @param DataConverterInterface $dataConverter
     */
    public function setDataConverter(DataConverterInterface $dataConverter);
}
