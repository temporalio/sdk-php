<?php

declare(strict_types=1);

namespace Temporal\Tests\Unit\Framework\Requests;

use Temporal\DataConverter\ValuesInterface;
use Temporal\Worker\Transport\Command\Request;

/**
 * @internal
 */
final class InvokeActivity extends Request
{
    public function __construct(string $activityName, ValuesInterface $values)
    {
        $options = [
            'name' => $activityName,
            'info' => [
                'TaskToken' => 'CiQ2ODM5YzcwOS05MGQwLTQ2ZjktOTYyYS03NTM3OWJhMWQ4MzcSJDQ5NDI1YjgwLTAwNTctNDA5Ni04ZWQyLTJmZjMzMzY5MmM3YxokOTI2MGFlZTMtYzhhMC00ZTMxLWI3ZWUtNWQ2NTZhYWEzMjZiIAUoATIBNUITU2ltcGxlQWN0aXZpdHkuZWNobw==',
                'ActivityType' => ['Name' => $activityName],
            ]
        ];
        parent::__construct('InvokeActivity', $options, $values);
    }
}
