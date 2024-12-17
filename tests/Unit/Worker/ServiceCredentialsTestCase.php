<?php

declare(strict_types=1);

namespace Temporal\Tests\Unit\Worker;

use PHPUnit\Framework\TestCase;
use Temporal\Worker\ServiceCredentials;

class ServiceCredentialsTestCase extends TestCase
{
    public function testWithApiKeyImmutability()
    {
        $dto = ServiceCredentials::create();

        $new = $dto->withApiKey('test');

        $this->assertNotSame($dto, $new);
        $this->assertSame('test', $new->apiKey);
        $this->assertSame('', $dto->apiKey);
    }
}
