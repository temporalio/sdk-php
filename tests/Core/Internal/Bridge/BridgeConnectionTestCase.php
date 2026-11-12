<?php

declare(strict_types=1);

namespace Temporal\Tests\Core\Internal\Bridge;

use PHPUnit\Framework\TestCase;
use Temporal\Internal\Bridge\BridgeConnection;

final class BridgeConnectionTestCase extends TestCase
{
    public function testUnreadableCertificateFileIsRejected(): void
    {
        $file = (string) \tempnam(\sys_get_temp_dir(), 'pem');
        \chmod($file, 0);
        try {
            $this->expectExceptionObject(new \InvalidArgumentException("Failed to load certificate from file `$file`."));
            @BridgeConnection::tls($file, null, null, null);
        } finally {
            \unlink($file);
        }
    }
}
