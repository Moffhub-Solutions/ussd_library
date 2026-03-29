<?php

declare(strict_types=1);

use Moffhub\Ussd\Services\TranslationService;

if (! function_exists('__ussd')) {
    /**
     * Translate a USSD string.
     *
     * @param  array<string, string>  $params
     */
    function __ussd(string $key, array $params = [], ?string $locale = null): string
    {
        /** @var TranslationService $service */
        $service = app(TranslationService::class);

        return $service->translate($key, $params, $locale);
    }
}
