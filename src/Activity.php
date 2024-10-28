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
     * ```php
     *  #[ActivityMethod]
     *  public function activityMethod(int $first, string $second)
     *  {
     *      $arguments = Activity::getInput();
     *
     *      Assert::assertTrue($first,  $arguments->getValue(0, Type::TYPE_INT));
     *      Assert::assertTrue($second, $arguments->getValue(1, Type::TYPE_STRING));
     *  }
     * ```
     *
     * @throws OutOfContextException in the absence of the activity execution context.
     */
    public static function getInput(): ValuesInterface
    {
        /** @var ActivityContextInterface $context */
        $context = self::getCurrentContext();

        return $context->getInput();
    }

    /**
     * Check if the heartbeat's first argument has been passed.
     *
     * This method returns **true** if the first argument has been passed to the {@see Activity::heartbeat()} method.
     *
     * @throws OutOfContextException in the absence of the activity execution context.
     */
    public static function hasHeartbeatDetails(): bool
    {
        /** @var ActivityContextInterface $context */
        $context = self::getCurrentContext();

        return $context->hasHeartbeatDetails();
    }

    /**
     * Returns the payload passed into the last heartbeat.
     *
     * This method retrieves the payload that was passed into the last call of the {@see Activity::heartbeat()} method.
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
     * Marks the activity as incomplete for asynchronous completion.
     *
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
     * ```php
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
     * ```
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
