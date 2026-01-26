<?php

declare(strict_types=1);

namespace Moffhub\Ussd\Providers;

use Illuminate\Http\Request;
use InvalidArgumentException;
use Moffhub\Ussd\Interfaces\UssdProviderInterface;

/**
 * Factory for creating USSD provider instances.
 *
 * Can auto-detect the provider from request headers/fields or create
 * a specific provider by name.
 */
class ProviderFactory
{
    /** @var array<string, class-string<UssdProviderInterface>> */
    protected static array $providers = [
        'safaricom' => SafaricomProvider::class,
        'africas_talking' => SafaricomProvider::class,
        'at' => SafaricomProvider::class,
        'airtel' => AirtelProvider::class,
        'mtn' => MtnProvider::class,
        'generic' => GenericProvider::class,
    ];

    /** @var array<string, UssdProviderInterface> */
    protected static array $instances = [];

    /**
     * Create a provider instance by name.
     *
     * @throws InvalidArgumentException
     */
    public static function create(string $name, array $config = []): UssdProviderInterface
    {
        $name = strtolower($name);

        if (! isset(self::$providers[$name])) {
            throw new InvalidArgumentException("Unknown USSD provider: {$name}");
        }

        $cacheKey = $name.':'.md5(serialize($config));

        if (! isset(self::$instances[$cacheKey])) {
            $providerClass = self::$providers[$name];
            $provider = new $providerClass;

            // Apply configuration
            if (! empty($config)) {
                self::applyConfig($provider, $config);
            }

            self::$instances[$cacheKey] = $provider;
        }

        return self::$instances[$cacheKey];
    }

    /**
     * Auto-detect the provider from the request.
     */
    public static function detect(Request $request, array $config = []): UssdProviderInterface
    {
        // Check for explicit provider in config
        if (! empty($config['provider'])) {
            return self::create($config['provider'], $config);
        }

        // Check User-Agent header
        $userAgent = strtolower($request->header('User-Agent', ''));

        if (str_contains($userAgent, 'africa') || str_contains($userAgent, 'safaricom')) {
            return self::create('safaricom', $config);
        }

        if (str_contains($userAgent, 'airtel')) {
            return self::create('airtel', $config);
        }

        if (str_contains($userAgent, 'mtn')) {
            return self::create('mtn', $config);
        }

        // Check for provider-specific field patterns
        if ($request->has('phoneNumber') && $request->has('sessionId')) {
            // Likely Africa's Talking / Safaricom format
            return self::create('safaricom', $config);
        }

        if ($request->has('UserAnswer') || $request->has('userAnswer')) {
            // MTN-specific field
            return self::create('mtn', $config);
        }

        if ($request->has('transactionId') && ! $request->has('sessionId')) {
            // Airtel often uses transactionId instead of sessionId
            return self::create('airtel', $config);
        }

        // Default to generic
        return self::create('generic', $config);
    }

    /**
     * Register a custom provider.
     *
     * @param  class-string<UssdProviderInterface>  $providerClass
     */
    public static function register(string $name, string $providerClass): void
    {
        self::$providers[strtolower($name)] = $providerClass;
    }

    /**
     * Get list of available provider names.
     *
     * @return array<int, string>
     */
    public static function available(): array
    {
        return array_keys(self::$providers);
    }

    /**
     * Check if a provider is registered.
     */
    public static function has(string $name): bool
    {
        return isset(self::$providers[strtolower($name)]);
    }

    /**
     * Clear cached provider instances.
     */
    public static function clearCache(): void
    {
        self::$instances = [];
    }

    /**
     * Apply configuration to a provider instance.
     */
    protected static function applyConfig(UssdProviderInterface $provider, array $config): void
    {
        if (isset($config['country_code']) && method_exists($provider, 'setCountryCode')) {
            $provider->setCountryCode($config['country_code']);
        }

        if (isset($config['max_message_length']) && method_exists($provider, 'setMaxMessageLength')) {
            $provider->setMaxMessageLength($config['max_message_length']);
        }

        if (isset($config['field_mappings']) && method_exists($provider, 'setFieldMappings')) {
            $provider->setFieldMappings($config['field_mappings']);
        }
    }
}
