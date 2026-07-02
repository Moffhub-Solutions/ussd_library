<?php

declare(strict_types=1);

namespace Moffhub\Ussd\Helpers;

use Closure;
use Moffhub\Ussd\Actions\CallbackAction;
use Moffhub\Ussd\Actions\NavigateAction;
use Moffhub\Ussd\Actions\SaveDataAction;
use Moffhub\Ussd\DataProviders\ApiDataProvider;
use Moffhub\Ussd\DataProviders\ArrayDataProvider;
use Moffhub\Ussd\DataProviders\DatabaseDataProvider;
use Moffhub\Ussd\Interfaces\MenuNameInterface;

class UssdHelpers
{
    public static function apiProvider(string $baseUrl, array $headers = [], mixed $auth = null): ApiDataProvider
    {
        return new ApiDataProvider($baseUrl, $headers, $auth);
    }

    public static function arrayProvider(array $data): ArrayDataProvider
    {
        return new ArrayDataProvider($data);
    }

    public static function callbackAction(Closure $callback): CallbackAction
    {
        return new CallbackAction($callback);
    }

    public static function databaseProvider(mixed $model, mixed $query = null): DatabaseDataProvider
    {
        return new DatabaseDataProvider($model, $query);
    }

    public static function formatCurrency(float|int $amount, string $currency = 'KES'): string
    {
        return $currency.' '.number_format($amount, 2);
    }

    public static function formatPhone(string $phone): string
    {
        $phone = preg_replace('/[^0-9]/', '', $phone) ?: '';

        if (strlen($phone) === 9) {
            $phone = '254'.$phone;
        } elseif (strlen($phone) === 10 && str_starts_with($phone, '0')) {
            $phone = '254'.substr($phone, 1);
        }

        return $phone;
    }

    public static function generateReference(string $prefix = 'REF', int $length = 8): string
    {
        $chars = '0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ';
        $reference = $prefix;

        for ($i = 0; $i < $length; $i++) {
            $reference .= $chars[random_int(0, strlen($chars) - 1)];
        }

        return $reference;
    }

    public static function navigateAction(
        string|MenuNameInterface|\BackedEnum $menuName,
        array $data = []
    ): NavigateAction {
        return new NavigateAction($menuName, $data);
    }

    public static function saveDataAction(
        Closure $callback,
        ?string $successMessage = null,
        ?string $errorMessage = null
    ): SaveDataAction {
        return new SaveDataAction($callback, $successMessage, $errorMessage);
    }

    public static function truncateText(string $text, int $length = 50, string $suffix = '...'): string
    {
        if (strlen($text) <= $length) {
            return $text;
        }

        return substr($text, 0, $length - strlen($suffix)).$suffix;
    }
}
