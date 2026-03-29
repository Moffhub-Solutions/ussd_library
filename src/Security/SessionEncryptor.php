<?php

declare(strict_types=1);

namespace Moffhub\Ussd\Security;

use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Log;

class SessionEncryptor
{
    protected bool $enabled;

    /** @var array<int, string> */
    protected array $encryptedFields;

    public function __construct(?bool $enabled = null, ?array $encryptedFields = null)
    {
        $this->enabled = $enabled ?? (bool) config('ussd.security.encrypt_session_data', false);
        $this->encryptedFields = $encryptedFields ?? (array) config('ussd.security.encrypted_fields', []);
    }

    /**
     * Encrypt sensitive fields in session data before saving.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function encrypt(array $data): array
    {
        if (! $this->enabled || $this->encryptedFields === []) {
            return $data;
        }

        return $this->processFields($data, true);
    }

    /**
     * Decrypt sensitive fields in session data after loading.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function decrypt(array $data): array
    {
        if (! $this->enabled || $this->encryptedFields === []) {
            return $data;
        }

        return $this->processFields($data, false);
    }

    /**
     * Process fields for encryption or decryption.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function processFields(array $data, bool $encrypting): array
    {
        foreach ($this->encryptedFields as $field) {
            $value = data_get($data, $field);

            if ($value === null) {
                continue;
            }

            try {
                if ($encrypting) {
                    $processed = Crypt::encryptString(is_string($value) ? $value : json_encode($value));
                } else {
                    $decrypted = Crypt::decryptString((string) $value);
                    // Try to decode JSON, fall back to string
                    $jsonDecoded = json_decode($decrypted, true);
                    $processed = (json_last_error() === JSON_ERROR_NONE && ! is_string($jsonDecoded)) ? $jsonDecoded : $decrypted;
                }

                data_set($data, $field, $processed);
            } catch (\Exception $e) {
                Log::warning('SessionEncryptor: Failed to process field', [
                    'field' => $field,
                    'operation' => $encrypting ? 'encrypt' : 'decrypt',
                    'error' => $e->getMessage(),
                ]);

                // On decryption failure, leave the value as-is
                // (it may already be unencrypted from before encryption was enabled)
            }
        }

        return $data;
    }

    /**
     * Check if encryption is enabled.
     */
    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    /**
     * Get the list of encrypted fields.
     *
     * @return array<int, string>
     */
    public function getEncryptedFields(): array
    {
        return $this->encryptedFields;
    }
}
