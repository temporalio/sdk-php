<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Tests\Unit\DTO;

use Temporal\DataConverter\DataConverter;
use Temporal\Internal\Marshaller\ProtoToArrayConverter;
use Temporal\Workflow\WorkflowExecution;

class WorkflowExecutionTestCase extends DTOMarshallingTestCase
{
    /**
     * @throws \ReflectionException
     */
    public function testMarshalling(): void
    {
        $dto = new WorkflowExecution();

        $expected = [
            'ID' => '00000000-0000-0000-0000-000000000000',
            'RunID' => null
        ];

        $this->assertSame($expected, $this->marshal($dto));
    }

    public function testUnmarshalFromProto(): void
    {
        $converter = DataConverter::createDefault();
        $protoConverter = new ProtoToArrayConverter($converter);
        $message = (new \Temporal\Api\Common\V1\WorkflowExecution())
            ->setWorkflowId('489-wf-id-test')
            ->setRunId('wf-run-id-test');
        $values = $protoConverter->convert($message);
        $dto = new WorkflowExecution();

        $result = $this->marshaller->unmarshal($values, $dto);

        $this->assertSame('489-wf-id-test', $result->getID());
        $this->assertSame('wf-run-id-test', $result->getRunID());
    }
}
