<?php

declare(strict_types=1);
/**
 * This file is part of Hyperf.
 *
 * @link     https://hyperf.org
 * @document https://wiki.hyperf.org
 * @contact  group@hyperf.org
 * @license  https://github.com/hyperf-cloud/hyperf/blob/master/LICENSE
 */

namespace Hyperf\Utils;

use Throwable;
use Psr\Log\LoggerInterface;
use Psr\Container\ContainerInterface;
use Swoole\Coroutine as SwooleCoroutine;
use Hyperf\Contract\StdoutLoggerInterface;

/**
 * @method static void defer(callable $callback)
 */
class Coroutine
{
    public static function __callStatic($name, $arguments)
    {
        if (! method_exists(SwooleCoroutine::class, $name)) {
            throw new \BadMethodCallException(sprintf('Call to undefined method %s.', $name));
        }
        return SwooleCoroutine::$name(...$arguments);
    }

    /**
     * Returns the current coroutine ID.
     * Returns -1 when running in non-coroutine context.
     */
    public static function id(): int
    {
        return SwooleCoroutine::getCid();
    }

    /**
     * @return int Returns the coroutine ID of the coroutine just created.
     *             Returns -1 when coroutine create failed.
     */
    public static function create(callable $callback): int
    {
        $result = SwooleCoroutine::create(function () use ($callback) {
            self::defer(function () {
                Context::destroy();
            });
            try {
                call($callback);
            } catch (Throwable $throwable) {
                $container = ApplicationContext::getContainer();
                if ($container instanceof ContainerInterface && $logger = $container->has(StdoutLoggerInterface::class)) {
                    /* @var LoggerInterface $logger */
                    $logger->warning(printf('Uncaptured exception[%s] detected in %s::%d.', get_class($throwable), $throwable->getFile(), $throwable->getLine()));
                }
            }
        });
        return is_int($result) ? $result : -1;
    }

    public static function inCoroutine(): bool
    {
        return Coroutine::id() > 0;
    }
}
