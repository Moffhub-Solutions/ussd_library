<?php

declare(strict_types=1);

namespace Moffhub\Ussd\Tests\Unit\Security;

use Moffhub\Ussd\Security\SessionEncryptor;
use Moffhub\Ussd\Tests\TestCase;

class SessionEncryptorTest extends TestCase
{
    protected function getEnvironmentSetUp($app): void
    {
        parent::getEnvironmentSetUp($app);

        // Ensure APP_KEY is set for encryption tests
        $app['config']->set('app.key', 'base64:'.base64_encode(random_bytes(32)));
    }

    public function test_encrypt_returns_data_unchanged_when_disabled(): void
    {
        $encryptor = new SessionEncryptor(enabled: false, encryptedFields: ['form_data.pin']);

        $data = ['form_data' => ['pin' => '1234', 'name' => 'John']];
        $result = $encryptor->encrypt($data);

        $this->assertEquals($data, $result);
    }

    public function test_decrypt_returns_data_unchanged_when_disabled(): void
    {
        $encryptor = new SessionEncryptor(enabled: false, encryptedFields: ['form_data.pin']);

        $data = ['form_data' => ['pin' => '1234', 'name' => 'John']];
        $result = $encryptor->decrypt($data);

        $this->assertEquals($data, $result);
    }

    public function test_encrypt_and_decrypt_round_trip(): void
    {
        $encryptor = new SessionEncryptor(enabled: true, encryptedFields: ['form_data.pin']);

        $original = ['form_data' => ['pin' => '1234', 'name' => 'John']];

        $encrypted = $encryptor->encrypt($original);

        // Pin should be encrypted (different from original)
        $this->assertNotEquals('1234', $encrypted['form_data']['pin']);
        // Name should be unchanged
        $this->assertEquals('John', $encrypted['form_data']['name']);

        $decrypted = $encryptor->decrypt($encrypted);

        // After decryption, pin should be back to original
        $this->assertEquals('1234', $decrypted['form_data']['pin']);
        $this->assertEquals('John', $decrypted['form_data']['name']);
    }

    public function test_encrypt_multiple_fields(): void
    {
        $encryptor = new SessionEncryptor(
            enabled: true,
            encryptedFields: ['form_data.pin', 'form_data.account_number'],
        );

        $original = [
            'form_data' => [
                'pin' => '1234',
                'account_number' => '9876543210',
                'name' => 'John',
            ],
        ];

        $encrypted = $encryptor->encrypt($original);

        $this->assertNotEquals('1234', $encrypted['form_data']['pin']);
        $this->assertNotEquals('9876543210', $encrypted['form_data']['account_number']);
        $this->assertEquals('John', $encrypted['form_data']['name']);

        $decrypted = $encryptor->decrypt($encrypted);

        $this->assertEquals('1234', $decrypted['form_data']['pin']);
        $this->assertEquals('9876543210', $decrypted['form_data']['account_number']);
        $this->assertEquals('John', $decrypted['form_data']['name']);
    }

    public function test_encrypt_handles_missing_field_gracefully(): void
    {
        $encryptor = new SessionEncryptor(
            enabled: true,
            encryptedFields: ['form_data.pin', 'form_data.nonexistent'],
        );

        $data = ['form_data' => ['name' => 'John']];

        $result = $encryptor->encrypt($data);

        // Should not throw, data should remain intact
        $this->assertEquals('John', $result['form_data']['name']);
    }

    public function test_decrypt_handles_unencrypted_data_gracefully(): void
    {
        $encryptor = new SessionEncryptor(
            enabled: true,
            encryptedFields: ['form_data.pin'],
        );

        // Data that was never encrypted (e.g., from before encryption was enabled)
        $data = ['form_data' => ['pin' => '1234']];

        // Should not throw - leaves value as-is on decryption failure
        $result = $encryptor->decrypt($data);

        $this->assertEquals('1234', $result['form_data']['pin']);
    }

    public function test_encrypt_array_value(): void
    {
        $encryptor = new SessionEncryptor(
            enabled: true,
            encryptedFields: ['user_data.secrets'],
        );

        $original = [
            'user_data' => [
                'secrets' => ['key1' => 'val1', 'key2' => 'val2'],
                'name' => 'John',
            ],
        ];

        $encrypted = $encryptor->encrypt($original);
        $decrypted = $encryptor->decrypt($encrypted);

        $this->assertEquals(['key1' => 'val1', 'key2' => 'val2'], $decrypted['user_data']['secrets']);
        $this->assertEquals('John', $decrypted['user_data']['name']);
    }

    public function test_is_enabled(): void
    {
        $enabled = new SessionEncryptor(enabled: true, encryptedFields: []);
        $disabled = new SessionEncryptor(enabled: false, encryptedFields: []);

        $this->assertTrue($enabled->isEnabled());
        $this->assertFalse($disabled->isEnabled());
    }

    public function test_get_encrypted_fields(): void
    {
        $fields = ['form_data.pin', 'form_data.account'];
        $encryptor = new SessionEncryptor(enabled: true, encryptedFields: $fields);

        $this->assertEquals($fields, $encryptor->getEncryptedFields());
    }

    public function test_encrypt_returns_unchanged_when_no_fields_configured(): void
    {
        $encryptor = new SessionEncryptor(enabled: true, encryptedFields: []);

        $data = ['form_data' => ['pin' => '1234']];
        $result = $encryptor->encrypt($data);

        $this->assertEquals($data, $result);
    }

    public function test_top_level_field_encryption(): void
    {
        $encryptor = new SessionEncryptor(
            enabled: true,
            encryptedFields: ['secret_value'],
        );

        $original = ['secret_value' => 'my-secret', 'public_value' => 'visible'];

        $encrypted = $encryptor->encrypt($original);

        $this->assertNotEquals('my-secret', $encrypted['secret_value']);
        $this->assertEquals('visible', $encrypted['public_value']);

        $decrypted = $encryptor->decrypt($encrypted);

        $this->assertEquals('my-secret', $decrypted['secret_value']);
    }
}
