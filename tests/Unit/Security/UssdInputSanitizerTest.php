<?php

declare(strict_types=1);

namespace Moffhub\Ussd\Tests\Unit\Security;

use Moffhub\Ussd\Security\UssdInputSanitizer;
use Moffhub\Ussd\Tests\TestCase;

class UssdInputSanitizerTest extends TestCase
{
    private UssdInputSanitizer $sanitizer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->sanitizer = new UssdInputSanitizer;
    }

    public function test_sanitize_valid_menu_option(): void
    {
        $result = $this->sanitizer->sanitize('1', 'menu_option');

        $this->assertTrue($result['valid']);
        $this->assertFalse($result['suspicious']);
        $this->assertEquals('1', $result['input']);
    }

    public function test_sanitize_valid_multi_digit_option(): void
    {
        $result = $this->sanitizer->sanitize('99', 'menu_option');

        $this->assertTrue($result['valid']);
        $this->assertEquals('99', $result['input']);
    }

    public function test_sanitize_text_input(): void
    {
        $result = $this->sanitizer->sanitize('Hello World', 'text');

        $this->assertTrue($result['valid']);
        $this->assertEquals('Hello World', $result['input']);
    }

    public function test_sanitize_phone_number(): void
    {
        $result = $this->sanitizer->sanitize('+254712345678', 'phone');

        $this->assertTrue($result['valid']);
        $this->assertStringContainsString('254712345678', $result['input']);
    }

    public function test_sanitize_trims_whitespace(): void
    {
        $result = $this->sanitizer->sanitize('  1  ', 'menu_option');

        $this->assertEquals('1', $result['input']);
    }

    public function test_sanitize_detects_sql_injection(): void
    {
        $result = $this->sanitizer->sanitize("1'; DROP TABLE users;--", 'text');

        $this->assertTrue($result['suspicious']);
        $this->assertContains('sql_injection', $result['reasons']);
    }

    public function test_sanitize_detects_script_injection(): void
    {
        $result = $this->sanitizer->sanitize('<script>alert("xss")</script>', 'text');

        $this->assertTrue($result['suspicious']);
    }

    public function test_sanitize_removes_control_characters(): void
    {
        $input = "Hello\x00World\x1F";
        $result = $this->sanitizer->sanitize($input, 'text');

        $this->assertStringNotContainsString("\x00", $result['input']);
        $this->assertStringNotContainsString("\x1F", $result['input']);
    }

    public function test_sanitize_empty_input(): void
    {
        $result = $this->sanitizer->sanitize('', 'menu_option');

        $this->assertTrue($result['valid']);
        $this->assertEquals('', $result['input']);
    }

    public function test_sanitize_numeric_input(): void
    {
        $result = $this->sanitizer->sanitize('12345', 'numeric');

        $this->assertTrue($result['valid']);
        $this->assertEquals('12345', $result['input']);
    }

    public function test_sanitize_alphanumeric_input(): void
    {
        $result = $this->sanitizer->sanitize('ABC123', 'alphanumeric');

        $this->assertTrue($result['valid']);
        $this->assertEquals('ABC123', $result['input']);
    }

    public function test_sanitize_special_characters_in_names(): void
    {
        $result = $this->sanitizer->sanitize("O'Brien", 'text');

        // Should handle apostrophes in names
        $this->assertTrue($result['valid']);
    }

    public function test_sanitize_unicode_text(): void
    {
        $result = $this->sanitizer->sanitize('你好世界', 'text');

        $this->assertTrue($result['valid']);
    }

    public function test_sanitize_email_format(): void
    {
        $result = $this->sanitizer->sanitize('test@example.com', 'email');

        $this->assertTrue($result['valid']);
        $this->assertEquals('test@example.com', $result['input']);
    }

    public function test_sanitize_max_length_enforcement(): void
    {
        $longInput = str_repeat('A', 1000);
        $result = $this->sanitizer->sanitize($longInput, 'text');

        // Should be truncated or flagged
        $this->assertLessThanOrEqual(500, strlen((string) $result['input']));
    }
}
