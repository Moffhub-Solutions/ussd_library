<?php

declare(strict_types=1);

namespace Moffhub\Ussd\Helpers;
class UssdEvents
{
    protected static array $listeners = [];

    public static function listen(string $event, callable $callback): void
    {
        if (!isset(static::$listeners[$event])) {
            static::$listeners[$event] = [];
        }

        static::$listeners[$event][] = $callback;
    }

    public static function fire(string $event, mixed $data = []): void
    {
        if (isset(static::$listeners[$event])) {
            foreach (static::$listeners[$event] as $callback) {
                if (is_callable($callback)) {
                    call_user_func($callback, $data);
                }
            }
        }
    }

    public static function clear(?string $event = null): void
    {
        if ($event) {
            unset(static::$listeners[$event]);
        } else {
            static::$listeners = [];
        }
    }
}
