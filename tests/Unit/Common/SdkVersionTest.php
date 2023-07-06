<?php

namespace Temporal\Tests\Unit\Common;

use PHPUnit\Framework\TestCase;
use Temporal\Common\SdkVersion;

class SdkVersionTest extends TestCase
{
    /**
     * @dataProvider versionProvider
     */
    public function testVersionRegx(string $version, string $matched): void
    {
        $result = preg_match(SdkVersion::VERSION_REGX, $version, $matches);
        if ($matched === '') {
            $this->assertNotSame(1, $result);
        } else {
            $this->assertEquals($matched, $matches[1]);
        }
    }

    public function versionProvider(): iterable
    {
        return [
            ['dev-master', ''],
            ['1.2.3-x-dev', '1.2.3-x-dev'],
            ['1.2.3-beta', '1.2.3-beta'],
            ['1.2.3-beta-1', '1.2.3-beta-1'],
            ['1.2.3-beta.1', '1.2.3-beta.1'],
            ['1.2.3-valhalla', '1.2.3-valhalla'],
            ['1.foo', ''],
            ['feature/interceptors', ''],
        ];
    }
}
