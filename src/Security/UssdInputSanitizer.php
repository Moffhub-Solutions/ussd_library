<?php

declare(strict_types=1);

namespace Moffhub\Ussd\Security;

use Illuminate\Support\Facades\Log;
use InvalidArgumentException;

class UssdInputSanitizer
{
    protected array $config;

    protected array $patterns;

    public function __construct($config = [])
    {
        $this->config = array_merge([
            'max_input_length' => 100,
            'allowed_chars' => 'a-zA-Z0-9\s\*\#\.\,\-\+\(\)',
            'blocked_patterns' => [
                '/script/i',
                '/<.*>/i',
                '/javascript/i',
                '/eval\(/i',
                '/exec\(/i',
                '/system\(/i',
                '/passthru\(/i',
                '/shell_exec/i',
                '/`.*`/i',
                '/\$\{.*\}/i',
                '/\$\(.*\)/i',
            ],
            'phone_validation' => true,
            'amount_validation' => true,
            'strict_mode' => false,
            'log_suspicious' => true,
        ], $config);

        $this->initializePatterns();
    }

    public function sanitize($input, $context = 'general'): array
    {
        $originalInput = $input;

        $input = $this->basicSanitization($input);

        $input = $this->contextualSanitization($input, $context);

        $validationResult = $this->validate($input, $context);

        if (! $validationResult['valid']) {
            $this->logSuspiciousInput($originalInput, $validationResult['reasons'], $context);

            if ($this->config['strict_mode']) {
                throw new InvalidArgumentException('Invalid input detected: '.implode(', ', $validationResult['reasons']));
            }

            return [
                'input' => $input,
                'valid' => false,
                'suspicious' => true,
                'reasons' => $validationResult['reasons'],
            ];
        }

        return [
            'input' => $input,
            'valid' => true,
            'suspicious' => false,
            'reasons' => [],
        ];
    }

    protected function basicSanitization($input): string
    {
        $input = trim((string) $input);

        if (strlen($input) > $this->config['max_input_length']) {
            $input = substr($input, 0, $this->config['max_input_length']);
        }

        $input = str_replace("\0", '', $input);
        $input = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', $input);

        return html_entity_decode($input, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }

    protected function contextualSanitization($input, $context): array|string|null
    {
        return match ($context) {
            'phone' => $this->sanitizePhone($input),
            'amount', 'money' => $this->sanitizeAmount($input),
            'name' => $this->sanitizeName($input),
            'menu_option' => $this->sanitizeMenuOption($input),
            'search' => $this->sanitizeSearch($input),
            default => $this->sanitizeGeneral($input),
        };
    }

    protected function validate($input, $context): array
    {
        $reasons = [];

        foreach ($this->config['blocked_patterns'] as $pattern) {
            if (preg_match($pattern, $input)) {
                $reasons[] = 'Contains blocked pattern';
                break;
            }
        }

        if (! preg_match('/^['.$this->config['allowed_chars'].']*$/', $input)) {
            $reasons[] = 'Contains invalid characters';
        }

        $contextValidation = $this->validateByContext($input, $context);
        if (! $contextValidation['valid']) {
            $reasons = array_merge($reasons, $contextValidation['reasons']);
        }

        return [
            'valid' => empty($reasons),
            'reasons' => $reasons,
        ];
    }

    protected function validateByContext($input, $context): array
    {
        return match ($context) {
            'phone' => $this->validatePhone($input),
            'amount', 'money' => $this->validateAmount($input),
            'menu_option' => $this->validateMenuOption($input),
            default => ['valid' => true, 'reasons' => []],
        };
    }

    protected function sanitizePhone($input): array|string|null
    {
        $input = preg_replace('/[^0-9+]/', '', $input);

        if (preg_match('/^0([7][0-9]{8})$/', $input, $matches)) {
            $input = '254'.$matches[1];
        } elseif (preg_match('/^\+254([7][0-9]{8})$/', $input, $matches)) {
            $input = '254'.$matches[1];
        } elseif (preg_match('/^254([7][0-9]{8})$/', $input)) {
            // Already in correct format
        }

        return $input;
    }

    protected function validatePhone($input): array
    {
        $reasons = [];

        if (! preg_match('/^254[7][0-9]{8}$/', $input)) {
            $reasons[] = 'Invalid phone number format';
        }

        return [
            'valid' => empty($reasons),
            'reasons' => $reasons,
        ];
    }

    protected function sanitizeAmount($input): array|string|null
    {
        $input = preg_replace('/[^\d\.]/', '', $input);

        if (substr_count($input, '.') > 1) {
            $parts = explode('.', $input);
            $input = $parts[0].'.'.implode('', array_slice($parts, 1));
        }

        return $input;
    }

    protected function validateAmount($input): array
    {
        $reasons = [];

        if (! is_numeric($input)) {
            $reasons[] = 'Invalid amount format';
        } elseif (floatval($input) < 0) {
            $reasons[] = 'Amount cannot be negative';
        } elseif (floatval($input) > 999999.99) {
            $reasons[] = 'Amount too large';
        }

        return [
            'valid' => empty($reasons),
            'reasons' => $reasons,
        ];
    }

    protected function sanitizeName($input): string
    {
        $input = preg_replace('/[^a-zA-Z\s\'\-]/', '', $input);

        $input = preg_replace('/\s+/', ' ', $input);

        return ucwords(strtolower($input));
    }

    protected function sanitizeMenuOption($input): string
    {
        $sanitized = trim((string) $input);

        $sanitized = str_replace("\0", '', $sanitized);
        $sanitized = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', $sanitized);

        if (strlen($sanitized) > 100) {
            $sanitized = substr($sanitized, 0, 100);
        }

        return $sanitized;
    }

    protected function validateMenuOption($input): array
    {
        $reasons = [];

        $input = is_scalar($input) ? trim((string) $input) : '';

        if (! preg_match('/^[a-zA-Z0-9\*\#\s\-\.]+$/', $input)) {
            $reasons[] = 'Contains invalid characters for USSD input';
        }

        if (preg_match('/<script|javascript:|eval\(|exec\(|system\(/i', $input)) {
            $reasons[] = 'Contains potentially malicious code';
        }

        if (strlen($input) > 200) {
            $reasons[] = 'Input too long for USSD';
        }

        Log::debug('Input validation result', [
            'input' => $input,
            'valid' => empty($reasons),
            'reasons' => $reasons,
        ]);

        return [
            'valid' => empty($reasons),
            'reasons' => $reasons,
        ];
    }

    protected function sanitizeSearch($input): array|string|null
    {
        $input = preg_replace('/[^a-zA-Z0-9\s\.\,\-]/', '', $input);

        return preg_replace('/\s+/', ' ', $input);
    }

    protected function sanitizeGeneral($input): array|string|null
    {
        return preg_replace('/[^a-zA-Z0-9\s\.\,\-]/', '', $input);
    }

    protected function initializePatterns(): void
    {
        $this->patterns = [
            'sql_injection' => '/(\bunion\b|\bselect\b|\binsert\b|\bupdate\b|\bdelete\b|\bdrop\b)/i',
            'script_injection' => '/<script|javascript:|vbscript:|onload=|onerror=/i',
            'path_traversal' => '/\.\.\/|\.\.\\\\/',
            'command_injection' => '/;|\|\||&&|\`|\$\(|\${/',
        ];
    }

    protected function logSuspiciousInput($input, $reasons, $context): void
    {
        if (! $this->config['log_suspicious']) {
            return;
        }

        Log::channel('security')->warning('Suspicious USSD input detected', [
            'input' => $input,
            'context' => $context,
            'reasons' => $reasons,
            'phone' => request()->input('phoneNumber'),
            'session_id' => request()->input('sessionId'),
            'timestamp' => now()->toDateTimeString(),
            'ip' => request()->ip() ?? 'unknown',
        ]);
    }

    public function getStats(): array
    {
        return [
            'config' => $this->config,
            'patterns_count' => count($this->patterns),
            'blocked_patterns_count' => count($this->config['blocked_patterns']),
        ];
    }

    public function isSuspicious($input): bool
    {
        return array_any($this->patterns, fn ($pattern) => preg_match($pattern, $input));

    }
}
