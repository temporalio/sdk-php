<?php

declare(strict_types=1);

namespace Temporal\Tests\Unit\Interceptor;

use Temporal\Interceptor\Header;
use Temporal\Interceptor\WorkflowInbound\InitInput;
use Temporal\Tests\Unit\AbstractUnit;
use Temporal\Workflow\WorkflowInfo;

/**
 * @group unit
 * @group interceptor
 */
class InitInputTestCase extends AbstractUnit
{
    public function testConstructor(): void
    {
        $info = new WorkflowInfo();
        $header = Header::empty();

        $input = new InitInput($info, $header);

        self::assertSame($info, $input->info);
        self::assertSame($header, $input->header);
    }

    public function testWithReturnsNewInstance(): void
    {
        $info = new WorkflowInfo();
        $header = Header::empty();
        $input = new InitInput($info, $header);

        $newInfo = new WorkflowInfo();
        $newInput = $input->with(info: $newInfo);

        self::assertNotSame($input, $newInput);
        self::assertSame($newInfo, $newInput->info);
        self::assertSame($header, $newInput->header);
    }

    public function testWithHeader(): void
    {
        $info = new WorkflowInfo();
        $header = Header::empty();
        $input = new InitInput($info, $header);

        $newHeader = Header::empty();
        $newInput = $input->with(header: $newHeader);

        self::assertNotSame($input, $newInput);
        self::assertSame($info, $newInput->info);
        self::assertSame($newHeader, $newInput->header);
    }

    public function testWithPreservesOriginalWhenNullArgs(): void
    {
        $info = new WorkflowInfo();
        $header = Header::empty();
        $input = new InitInput($info, $header);

        $newInput = $input->with();

        self::assertNotSame($input, $newInput);
        self::assertSame($info, $newInput->info);
        self::assertSame($header, $newInput->header);
    }
}
