<?php

declare(strict_types=1);

namespace Moffhub\Ussd\Helpers;


class UssdHelpers
{
    public static function arrayProvider(array $data): ArrayDataProvider
    {
        return new ArrayDataProvider($data);
    }

    public static function databaseProvider(mixed $model, mixed $query = null): DatabaseDataProvider
    {
        return new DatabaseDataProvider($model, $query);
    }

    public static function apiProvider(string $baseUrl, array $headers = [], mixed $auth = null): ApiDataProvider
    {
        return new ApiDataProvider($baseUrl, $headers, $auth);
    }

    public static function navigateAction(string $menuName, array $data = []): NavigateAction
    {
        return new NavigateAction($menuName, $data);
    }

    public static function callbackAction(callable $callback): CallbackAction
    {
        return new CallbackAction($callback);
    }

    public static function saveDataAction(callable $callback, ?string $successMessage = null, ?string $errorMessage = null): SaveDataAction
    {
        return new SaveDataAction($callback, $successMessage, $errorMessage);
    }

    public static function formatCurrency(float|int $amount, string $currency = 'KES'): string
    {
        return $currency.' '.number_format($amount, 2);
    }

    public static function formatPhone(string $phone): string
    {
        // Remove all non-numeric characters
        $phone = preg_replace('/[^0-9]/', '', $phone);

        // Add country code if missing
        if (strlen($phone) === 9) {
            $phone = '254'.$phone;
        } elseif (strlen($phone) === 10 && substr($phone, 0, 1) === '0') {
            $phone = '254'.substr($phone, 1);
        }

        return $phone;
    }

    public static function truncateText(string $text, int $length = 50, string $suffix = '...'): string
    {
        if (strlen($text) <= $length) {
            return $text;
        }

        return substr($text, 0, $length - strlen($suffix)).$suffix;
    }

    public static function generateReference(string $prefix = 'REF', int $length = 8): string
    {
        $chars = '0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ';
        $reference = $prefix;

        for ($i = 0; $i < $length; $i++) {
            $reference .= $chars[rand(0, strlen($chars) - 1)];
        }

        return $reference;
    }
}
