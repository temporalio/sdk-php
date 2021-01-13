<?php


namespace Temporal\Tests\DataConverter;


use Temporal\DataConverter\DataConverter;
use Temporal\Tests\TestCase;

class DataConverterTestCase extends TestCase
{
    public function testConvert()
    {
        $dc = DataConverter::createDefault();

        $payload = $dc->toPayload('abc');
        $this->assertSame('abc', $dc->fromPayload($payload, 'string'));
    }
}
