<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal;

use Temporal\Activity\ActivityContextInterface;
use Temporal\Activity\ActivityInfo;
use Temporal\DataConverter\Type;
use Temporal\DataConverter\ValuesInterface;
use Temporal\Exception\OutOfContextException;
use Temporal\Internal\Support\Facade;

/**
 * @template-extends Facade<ActivityContextInterface>
 */
final class Activity extends Facade
{
    /**
     * Returns information about current activity execution.
     *
     * @return ActivityInfo
     * @throws OutOfContextException in the absence of the activity execution context.
     */
    public static function getInfo(): ActivityInfo
    {
        /** @var ActivityContextInterface $context */
        $context = self::getCurrentContext();

        return $context->getInfo();
    }

    /**
     * Returns activity execution input arguments.
     *
     * The data is equivalent to what is passed to the activity method handler.
     *
     * <code>
     *  #[ActivityMethod]
     *  public function activityMethod(int $first, string $second)
     *  {
     *      $arguments = Activity::getInput();
     *
     *      Assert::assertTrue($first,  $arguments->getValue(0, Type::TYPE_INT));
     *      Assert::assertTrue($second, $arguments->getValue(1, Type::TYPE_STRING));
     *  }
     * </code>
     *
     * @return ValuesInterface
     * @throws OutOfContextException in the absence of the activity execution context.
     */
    public static function getInput(): ValuesInterface
    {
        /** @var ActivityContextInterface $context */
        $context = self::getCurrentContext();

        return $context->getInput();
    }

    /**
     * Returns {@see true} when heartbeat's ({@see Activity::heartbeat()}) first
     * argument has been passed.
     *
     * @return bool
     * @throws OutOfContextException in the absence of the activity execution context.
     */
    public static function hasHeartbeatDetails(): bool
    {
        /** @var ActivityContextInterface $context */
        $context = self::getCurrentContext();

        return $context->hasHeartbeatDetails();
    }

    /**
     * The method returns payload that has been passed into last
     * heartbeat ({@see Activity::heartbeat()}) method.
     *
     * @param Type|string|\ReflectionType|\ReflectionClass|null $type
     * @return mixed
     * @throws OutOfContextException in the absence of the activity execution context.
     */
    public static function getHeartbeatDetails($type = null)
    {
        /** @var ActivityContextInterface $context */
        $context = self::getCurrentContext();

        return $context->getHeartbeatDetails($type);
    }

    /**
     * If this method is called during an activity execution then activity is
     * not going to complete when its method returns. It is expected to be
     * completed asynchronously using {@see ActivityCompletionClientInterface::complete()}.
     *
     * @throws OutOfContextException in the absence of the activity execution context.
     */
    public static function doNotCompleteOnReturn(): void
    {
        /** @var ActivityContextInterface $context */
        $context = self::getCurrentContext();

        $context->doNotCompleteOnReturn();
    }

    /**
     * Use to notify workflow that activity execution is alive.
     *
     * <code>
     *  public function activityMethod()
     *  {
     *      // An example method of deferred request
     *      $query = $this->db->query('SELECT * FROM table WHERE 1=1');
     *
     *      // Wait for response
     *      while (!$query->isCompleted()) {
     *          Activity::heartbeat('Waiting for activity response');
     *      }
     *
     *      // Returns response of deferred request
     *      return $query->getResult();
     *  }
     * </code>
     *
     * @param mixed $details In case of activity timeout details are returned
     * as a field of the exception thrown.
     * @throws OutOfContextException in the absence of the activity execution context.
     */
    public static function heartbeat($details): void
    {
        /** @var ActivityContextInterface $context */
        $context = self::getCurrentContext();

        $context->heartbeat($details);
    }
}
