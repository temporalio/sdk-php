<?php

declare(strict_types=1);

namespace Temporal\Tests\Unit\Internal\Marshaller;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Temporal\Api\Common\V1\Payload;
use Temporal\Api\Common\V1\SearchAttributes;
use Temporal\Api\Schedule\V1\Range;
use Temporal\Api\Schedule\V1\Schedule;
use Temporal\Api\Schedule\V1\ScheduleSpec;
use Temporal\Api\Schedule\V1\StructuredCalendarSpec;
use Temporal\Api\Workflowservice\V1\DescribeScheduleResponse;
use Temporal\DataConverter\DataConverter;
use Temporal\Internal\Marshaller\ProtoToArrayConverter;

/**
 * @internal
 */
#[CoversClass(ProtoToArrayConverter::class)]
final class ProtoToArrayConverterTestCase extends TestCase
{
    public function testRepeatedFieldIsConvertedToArray(): void
    {
        $spec = (new ScheduleSpec())->setStructuredCalendar([$this->structuredCalendar()]);

        $result = $this->converter()->convert($spec);

        $this->assertIsArray($result['structured_calendar']);
        $this->assertCount(1, $result['structured_calendar']);
    }

    public function testNestedRepeatedFieldIsConvertedToArray(): void
    {
        $response = (new DescribeScheduleResponse())->setSchedule(
            (new Schedule())->setSpec(
                (new ScheduleSpec())->setStructuredCalendar([$this->structuredCalendar()]),
            ),
        );

        $result = $this->converter()->convert($response);

        $this->assertIsArray($result['schedule']['spec']['structured_calendar']);
    }

    public function testRepeatedFieldOfMessagesIsConvertedElementWise(): void
    {
        $spec = (new StructuredCalendarSpec())->setSecond([
            (new Range())->setStart(1)->setEnd(2)->setStep(3),
        ]);

        $result = $this->converter()->convert($spec);

        $this->assertSame(1, $result['second'][0]['start']);
        $this->assertSame(2, $result['second'][0]['end']);
        $this->assertSame(3, $result['second'][0]['step']);
    }

    public function testEmptyRepeatedFieldIsConvertedToEmptyArray(): void
    {
        $result = $this->converter()->convert(new ScheduleSpec());

        $this->assertSame([], $result['structured_calendar']);
    }

    public function testMapFieldIsConvertedToArray(): void
    {
        $attributes = new SearchAttributes();
        $attributes->getIndexedFields()['foo'] = (new Payload())
            ->setMetadata(['encoding' => 'json/plain'])
            ->setData('"bar"');

        $result = $this->converter()->convert($attributes);

        $this->assertSame('bar', $result->getValue('foo'));
    }

    private function structuredCalendar(): StructuredCalendarSpec
    {
        return (new StructuredCalendarSpec())
            ->setSecond([(new Range())->setStart(0)->setEnd(0)->setStep(1)])
            ->setComment('every minute');
    }

    private function converter(): ProtoToArrayConverter
    {
        return new ProtoToArrayConverter(DataConverter::createDefault());
    }
}
