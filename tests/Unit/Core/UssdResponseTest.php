<?php

declare(strict_types=1);

namespace Moffhub\Ussd\Tests\Unit\Core;

use Moffhub\Ussd\Tests\TestCase;
use Moffhub\Ussd\UssdResponse;

class UssdResponseTest extends TestCase
{
    public function test_continue_creates_continue_response(): void
    {
        $response = UssdResponse::continue('Hello World');

        $this->assertEquals('Hello World', $response->getMessage());
        $this->assertEquals(UssdResponse::CONTINUE, $response->getType());
        $this->assertTrue($response->isContinue());
        $this->assertFalse($response->isEnd());
    }

    public function test_end_creates_end_response(): void
    {
        $response = UssdResponse::end('Goodbye');

        $this->assertEquals('Goodbye', $response->getMessage());
        $this->assertEquals(UssdResponse::END, $response->getType());
        $this->assertTrue($response->isEnd());
        $this->assertFalse($response->isContinue());
    }

    public function test_format_for_network_returns_correct_format(): void
    {
        $continueResponse = UssdResponse::continue('Test message');
        $endResponse = UssdResponse::end('End message');

        $this->assertEquals('CON Test message', $continueResponse->formatForNetwork());
        $this->assertEquals('END End message', $endResponse->formatForNetwork());
    }

    public function test_menu_creates_formatted_menu(): void
    {
        $options = [
            '1' => 'Option One',
            '2' => 'Option Two',
            '3' => 'Option Three',
        ];

        $response = UssdResponse::menu('Main Menu', $options);

        $this->assertStringContainsString('Main Menu', $response->getMessage());
        $this->assertStringContainsString('1. Option One', $response->getMessage());
        $this->assertStringContainsString('2. Option Two', $response->getMessage());
        $this->assertStringContainsString('3. Option Three', $response->getMessage());
        $this->assertTrue($response->isContinue());
    }

    public function test_error_creates_error_response(): void
    {
        $response = UssdResponse::error('Something went wrong');

        $this->assertEquals('Something went wrong', $response->getMessage());
        $this->assertTrue($response->isError());
        $this->assertTrue($response->isContinue());
    }

    public function test_error_with_end_flag(): void
    {
        $response = UssdResponse::error('Critical error', true);

        $this->assertTrue($response->isEnd());
    }

    public function test_success_creates_success_response(): void
    {
        $response = UssdResponse::success('Operation completed');

        $this->assertEquals('Operation completed', $response->getMessage());
        $this->assertTrue($response->isSuccess());
        $this->assertTrue($response->isEnd());
    }

    public function test_success_with_continue_flag(): void
    {
        $response = UssdResponse::success('Operation completed', false);

        $this->assertTrue($response->isContinue());
    }

    public function test_form_creates_form_field_response(): void
    {
        $response = UssdResponse::form('Enter your name:', true);

        $this->assertStringContainsString('Enter your name:', $response->getMessage());
        $this->assertEquals('form_field', $response->getMetadataValue('type'));
        $this->assertTrue($response->getMetadataValue('required'));
    }

    public function test_form_with_validation_error(): void
    {
        $response = UssdResponse::form('Enter your name:', true, 'Name is required');

        $this->assertStringContainsString('Error: Name is required', $response->getMessage());
        $this->assertStringContainsString('Enter your name:', $response->getMessage());
    }

    public function test_form_optional_field_shows_skip_message(): void
    {
        $response = UssdResponse::form('Enter nickname:', false);

        $this->assertStringContainsString('(Optional - press * to skip)', $response->getMessage());
    }

    public function test_pagination_shows_navigation(): void
    {
        $response = UssdResponse::pagination('Content here', 2, 5, true, true);

        $this->assertStringContainsString('Content here', $response->getMessage());
        $this->assertStringContainsString('00. Next page', $response->getMessage());
        $this->assertStringContainsString('99. Previous page', $response->getMessage());
        $this->assertStringContainsString('Page 2 of 5', $response->getMessage());
    }

    public function test_pagination_hides_navigation_on_single_page(): void
    {
        $response = UssdResponse::pagination('Content here', 1, 1, false, false);

        $this->assertStringNotContainsString('00. Next page', $response->getMessage());
        $this->assertStringNotContainsString('99. Previous page', $response->getMessage());
    }

    public function test_progress_shows_percentage(): void
    {
        $response = UssdResponse::progress('Processing...', 3, 10);

        $this->assertStringContainsString('Progress: 30%', $response->getMessage());
        $this->assertStringContainsString('Step 3 of 10', $response->getMessage());
        $this->assertEquals(30, $response->getMetadataValue('percentage'));
    }

    public function test_confirmation_creates_yes_no_prompt(): void
    {
        $response = UssdResponse::confirmation('Are you sure?');

        $this->assertStringContainsString('Are you sure?', $response->getMessage());
        $this->assertStringContainsString('1. Yes', $response->getMessage());
        $this->assertStringContainsString('2. No', $response->getMessage());
    }

    public function test_confirmation_with_custom_text(): void
    {
        $response = UssdResponse::confirmation('Confirm?', "1. Confirm\n2. Cancel");

        $this->assertStringContainsString('1. Confirm', $response->getMessage());
        $this->assertStringContainsString('2. Cancel', $response->getMessage());
    }

    public function test_search_creates_search_prompt(): void
    {
        $response = UssdResponse::search('Enter search term:');

        $this->assertStringContainsString('Enter search term:', $response->getMessage());
        $this->assertEquals('search', $response->getMetadataValue('type'));
    }

    public function test_search_shows_current_query(): void
    {
        $response = UssdResponse::search('Enter new search:', 'previous query');

        $this->assertStringContainsString("Current search: 'previous query'", $response->getMessage());
    }

    public function test_timeout_creates_timeout_response(): void
    {
        $response = UssdResponse::timeout();

        $this->assertStringContainsString('Session timed out', $response->getMessage());
        $this->assertTrue($response->isEnd());
        $this->assertEquals('timeout', $response->getMetadataValue('type'));
    }

    public function test_validation_error_creates_error_response(): void
    {
        $response = UssdResponse::validationError('email', 'Invalid email format', 'Enter your email:');

        $this->assertStringContainsString('Error: Invalid email format', $response->getMessage());
        $this->assertStringContainsString('Enter your email:', $response->getMessage());
        $this->assertTrue($response->isValidationError());
        $this->assertEquals('email', $response->getMetadataValue('field'));
    }

    public function test_navigate_creates_navigation_response(): void
    {
        $response = UssdResponse::navigate('next_menu', 'Loading...', ['key' => 'value']);

        $this->assertEquals('navigate', $response->getAction());
        $this->assertEquals('next_menu', $response->getMetadataValue('menu'));
        $this->assertEquals(['key' => 'value'], $response->getMetadataValue('data'));
    }

    public function test_back_creates_back_response(): void
    {
        $response = UssdResponse::back('Going back');

        $this->assertEquals('back', $response->getAction());
        $this->assertTrue($response->hasAction());
    }

    public function test_reset_creates_reset_response(): void
    {
        $response = UssdResponse::reset('Starting over');

        $this->assertEquals('reset', $response->getAction());
    }

    public function test_loading_creates_loading_response(): void
    {
        $response = UssdResponse::loading();

        $this->assertStringContainsString('Processing', $response->getMessage());
        $this->assertEquals('loading', $response->getMetadataValue('type'));
    }

    public function test_append_message(): void
    {
        $response = UssdResponse::continue('Hello');
        $response->appendMessage(' World');

        $this->assertEquals('Hello World', $response->getMessage());
    }

    public function test_prepend_message(): void
    {
        $response = UssdResponse::continue('World');
        $response->prependMessage('Hello ');

        $this->assertEquals('Hello World', $response->getMessage());
    }

    public function test_add_navigation(): void
    {
        $response = UssdResponse::continue('Menu content');
        $response->addNavigation(['0. Home', '99. Back']);

        $this->assertStringContainsString('0. Home', $response->getMessage());
        $this->assertStringContainsString('99. Back', $response->getMessage());
    }

    public function test_metadata_operations(): void
    {
        $response = UssdResponse::continue('Test');

        $response->addMetadata('key1', 'value1');
        $response->addMetadata('key2', 'value2');

        $this->assertEquals('value1', $response->getMetadataValue('key1'));
        $this->assertEquals('value2', $response->getMetadataValue('key2'));
        $this->assertNull($response->getMetadataValue('nonexistent'));
        $this->assertEquals('default', $response->getMetadataValue('nonexistent', 'default'));

        $response->removeMetadata('key1');
        $this->assertNull($response->getMetadataValue('key1'));
    }

    public function test_set_metadata(): void
    {
        $response = UssdResponse::continue('Test');
        $response->setMetadata(['new_key' => 'new_value']);

        $this->assertEquals(['new_key' => 'new_value'], $response->getMetadata());
    }

    public function test_truncate_message(): void
    {
        $longMessage = str_repeat('A', 200);
        $response = UssdResponse::continue($longMessage);
        $response->truncateMessage(100);

        $this->assertEquals(100, strlen($response->getMessage()));
        $this->assertStringEndsWith('...', $response->getMessage());
    }

    public function test_message_sanitization_removes_extra_whitespace(): void
    {
        $response = UssdResponse::continue("Hello    World\n\n\n  Test");

        // Should normalize whitespace
        $this->assertStringNotContainsString('    ', $response->getMessage());
    }

    public function test_message_truncation_for_long_messages(): void
    {
        $longMessage = str_repeat('A', 2000);
        $response = UssdResponse::continue($longMessage);

        $this->assertLessThanOrEqual(1600, strlen($response->getMessage()));
        $this->assertStringEndsWith('...', $response->getMessage());
    }

    public function test_to_array(): void
    {
        $response = UssdResponse::continue('Test', ['key' => 'value']);
        $array = $response->toArray();

        $this->assertArrayHasKey('message', $array);
        $this->assertArrayHasKey('type', $array);
        $this->assertArrayHasKey('metadata', $array);
        $this->assertArrayHasKey('formatted', $array);
        $this->assertArrayHasKey('is_continue', $array);
        $this->assertArrayHasKey('is_end', $array);
    }

    public function test_to_json(): void
    {
        $response = UssdResponse::continue('Test');
        $json = $response->toJson();

        $this->assertJson($json);
        $decoded = json_decode($json, true);
        $this->assertEquals('Test', $decoded['message']);
    }

    public function test_to_string_returns_formatted(): void
    {
        $response = UssdResponse::continue('Test');

        $this->assertEquals('CON Test', (string) $response);
    }

    public function test_set_message(): void
    {
        $response = UssdResponse::continue('Original');
        $response->setMessage('New message');

        $this->assertEquals('New message', $response->getMessage());
    }

    public function test_set_type(): void
    {
        $response = UssdResponse::continue('Test');
        $response->setType(UssdResponse::END);

        $this->assertTrue($response->isEnd());
    }

    public function test_should_continue(): void
    {
        $continueResponse = UssdResponse::continue('Test');
        $endResponse = UssdResponse::end('Test');

        $this->assertTrue($continueResponse->isContinue());
        $this->assertFalse($endResponse->isContinue());
    }

    public function test_debug_info(): void
    {
        $response = UssdResponse::continue('Test');
        $response->addDebugInfo(['step' => 1, 'menu' => 'main']);

        $this->assertEquals(['step' => 1, 'menu' => 'main'], $response->getDebugInfo());
    }
}
