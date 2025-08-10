<?php

declare(strict_types=1);

namespace App\Libraries\Ussd\Helpers;

use Closure;
use DateTime;

class Validators
{
    public static function required(?string $message = 'This field is required'): Closure
    {
        return function ($input) use ($message) {
            return !empty(trim($input)) ? true : $message;
        };
    }

    public static function minLength(int $min, ?string $message = null): Closure
    {
        return function ($input) use ($min, $message) {
            $message = $message ?? "Must be at least {$min} characters";

            return strlen($input) >= $min ? true : $message;
        };
    }

    public static function maxLength(int $max, ?string $message = null): Closure
    {
        return function ($input) use ($max, $message) {
            $message = $message ?? "Must not exceed {$max} characters";

            return strlen($input) <= $max ? true : $message;
        };
    }

    public static function numeric(?string $message = 'Must be a number'): Closure
    {
        return function ($input) use ($message) {
            return is_numeric($input) ? true : $message;
        };
    }

    public static function length(int $min, int $max, ?string $message = null): Closure
    {
        return function ($input) use ($min, $max, $message) {
            $length = strlen($input);
            if ($length < $min || $length > $max) {
                return $message ?? "Must be between $min and $max characters";
            }

            return true;
        };
    }

    public static function phone(?string $message = 'Invalid phone number format'): Closure
    {
        return function ($input) use ($message) {
            return preg_match('/^[0-9+\-\s]+$/', $input) ? true : $message;
        };
    }

    public static function email(?string $message = 'Invalid email format'): Closure
    {
        return function ($input) use ($message) {
            return filter_var($input, FILTER_VALIDATE_EMAIL) ? true : $message;
        };
    }

    public static function inOptions(array $options, ?string $message = 'Invalid option selected'): Closure
    {
        return function ($input) use ($options, $message) {
            return in_array($input, $options) ? true : $message;
        };
    }

    public static function regex(string $pattern, ?string $message = 'Invalid format'): Closure
    {
        return function ($input) use ($pattern, $message) {
            return preg_match($pattern, $input) ? true : $message;
        };
    }

    public static function custom(callable $callback, ?string $message = 'Invalid input'): Closure
    {
        return function ($input) use ($callback, $message) {
            return $callback($input) ? true : $message;
        };
    }

    public static function dob(?string $message = 'Invalid date of birth format', int $minAge = 18): Closure
    {
        return function ($input) use ($message, $minAge) {
            $date = DateTime::createFromFormat('Y-m-d', $input);
            if (!$date || $date->format('Y-m-d') !== $input) {
                return $message;
            }

            $now = new DateTime;

            if ($date > $now) {
                return 'Date of birth must be in the past';
            }

            $minDob = (clone $now)->modify("-$minAge years");
            if ($date > $minDob) {
                return "You must be at least {$minAge} years old";
            }

            return true;
        };
    }
}
