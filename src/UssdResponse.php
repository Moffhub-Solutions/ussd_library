<?php

namespace Moffhub\Ussd;

class UssdResponse
{
    public const string CONTINUE = 'CON';

    public const string END = 'END';

    protected ?array $debugInfo = null;

    protected string $message;

    protected array $metadata = [];

    protected string $type;

    public function __construct(string $message, string $type = self::CONTINUE, array $metadata = [])
    {
        $this->message = $this->sanitizeMessage($message);
        $this->type = $type;
        $this->metadata = $metadata;
    }

    protected function sanitizeMessage(string $message): string
    {
        // Remove excessive whitespace
        $message = preg_replace('/\s+/', ' ', $message);

        // Normalize line breaks
        $message = str_replace(["\r\n", "\r"], "\n", $message);

        // Remove leading/trailing whitespace from each line
        $lines = explode("\n", $message);
        $lines = array_map('trim', $lines);

        // Remove empty lines at the beginning and end
        while (! empty($lines) && empty($lines[0])) {
            array_shift($lines);
        }
        while (! empty($lines) && empty($lines[count($lines) - 1])) {
            array_pop($lines);
        }

        $message = implode("\n", $lines);

        // Ensure message doesn't exceed SMS limits (with some buffer)
        $maxLength = 1600; // Conservative limit
        if (strlen($message) > $maxLength) {
            $message = substr($message, 0, $maxLength - 3).'...';
        }

        return trim($message);
    }

    /**
     * Create a response that goes back to previous menu
     */
    public static function back(string $message = ''): self
    {
        return new self($message, self::CONTINUE, [
            'action' => 'back',
        ]);
    }

    public static function confirmation(string $message, string $confirmText = "1. Yes\n2. No"): self
    {
        $fullMessage = $message."\n\n".$confirmText;

        return self::continue($fullMessage, [
            'type' => 'confirmation',
        ]);
    }

    /**
     * Create an end response
     */
    public static function end(string $message, array $metadata = []): self
    {
        return new self($message, self::END, $metadata);
    }

    /**
     * Create an error response
     */
    public static function error(string $message = 'An error occurred. Please try again.', bool $end = false): self
    {
        return new self($message, $end ? self::END : self::CONTINUE, [
            'type' => 'error',
            'timestamp' => now()->toISOString(),
        ]);
    }

    public static function form(string $fieldPrompt, bool $isRequired = true, ?string $validationError = null): self
    {
        $message = $fieldPrompt;

        if ($validationError) {
            $message = "Error: {$validationError}\n\n{$message}";
        }

        if (! $isRequired) {
            $message .= "\n(Optional - press * to skip)";
        }

        return self::continue($message, [
            'type' => 'form_field',
            'required' => $isRequired,
            'validation_error' => $validationError,
        ]);
    }

    /**
     * Create a loading response
     */
    public static function loading(string $message = 'Processing... Please wait.'): self
    {
        return new self($message, self::CONTINUE, [
            'type' => 'loading',
            'timestamp' => now()->toISOString(),
        ]);
    }

    public static function menu(string $title, array $options, array $navigationOptions = []): self
    {
        $message = $title;

        if (! empty($options)) {
            $message .= "\n\n";
            foreach ($options as $key => $value) {
                $message .= "{$key}. {$value}\n";
            }
        }

        $response = self::continue($message);

        if (! empty($navigationOptions)) {
            $response->addNavigation($navigationOptions);
        }

        return $response;
    }

    /**
     * Create a continue response
     */
    public static function continue(string $message, array $metadata = []): self
    {
        return new self($message, self::CONTINUE, $metadata);
    }

    public function addNavigation(array $navigationOptions): self
    {
        if (! empty($navigationOptions)) {
            $navText = "\n\n".implode("\n", $navigationOptions);
            $this->appendMessage($navText);
        }

        return $this;
    }

    // Getters

    public function appendMessage(string $message): self
    {
        $this->message .= $message;
        $this->message = $this->sanitizeMessage($this->message);

        return $this;
    }

    /**
     * Create a response that navigates to another menu
     */
    public static function navigate(string $menuName, string $message = '', array $data = []): self
    {
        return new self($message, self::CONTINUE, [
            'action' => 'navigate',
            'menu' => $menuName,
            'data' => $data,
        ]);
    }

    public static function pagination(
        string $content,
        int $currentPage,
        int $totalPages,
        bool $hasNext = false,
        bool $hasPrevious = false
    ): self {
        $message = $content;

        if ($totalPages > 1) {
            $message .= "\n\n";

            if ($hasNext) {
                $message .= "00. Next page\n";
            }
            if ($hasPrevious) {
                $message .= "99. Previous page\n";
            }

            $message .= "Page {$currentPage} of {$totalPages}";
        }

        return self::continue($message, [
            'type' => 'pagination',
            'current_page' => $currentPage,
            'total_pages' => $totalPages,
            'has_next' => $hasNext,
            'has_previous' => $hasPrevious,
        ]);
    }

    public static function progress(string $message, int $currentStep, int $totalSteps): self
    {
        $percentage = $totalSteps > 0 ? round(($currentStep / $totalSteps) * 100) : 0;
        $progressMessage = "Progress: {$percentage}% (Step {$currentStep} of {$totalSteps})\n\n{$message}";

        return self::continue($progressMessage, [
            'type' => 'progress',
            'current_step' => $currentStep,
            'total_steps' => $totalSteps,
            'percentage' => $percentage,
        ]);
    }

    // Setters and modifiers

    /**
     * Create a response that resets the session
     */
    public static function reset(string $message = ''): self
    {
        return new self($message, self::CONTINUE, [
            'action' => 'reset',
        ]);
    }

    public static function search(string $prompt = 'Enter search term:', ?string $currentQuery = null): self
    {
        $message = $prompt;

        if ($currentQuery) {
            $message = "Current search: '{$currentQuery}'\n\n{$message}";
        }

        return self::continue($message, [
            'type' => 'search',
            'current_query' => $currentQuery,
        ]);
    }

    /**
     * Create a success response
     */
    public static function success(string $message, bool $end = true): self
    {
        return new self($message, $end ? self::END : self::CONTINUE, [
            'type' => 'success',
            'timestamp' => now()->toISOString(),
        ]);
    }

    /**
     * Create a timeout response
     */
    public static function timeout(string $message = 'Session timed out. Please try again.'): self
    {
        return new self($message, self::END, [
            'type' => 'timeout',
            'timestamp' => now()->toISOString(),
        ]);
    }

    /**
     * Create a response with form validation errors
     */
    public static function validationError(string $field, string $error, string $prompt): self
    {
        $message = "Error: {$error}\n\n{$prompt}";

        return new self($message, self::CONTINUE, [
            'type' => 'validation_error',
            'field' => $field,
            'error' => $error,
        ]);
    }

    public function __toArray(): array
    {
        return $this->toArray();
    }

    public function __toString(): string
    {
        return $this->formatForNetwork();
    }

    // State checks

    public function addDebugInfo(array $debugInfo): self
    {
        $this->debugInfo = $debugInfo;
        if (config('app.debug', false)) {
            $this->addMetadata('debug', $debugInfo);
        }

        return $this;
    }

    public function addMetadata(string $key, mixed $value): self
    {
        $this->metadata[$key] = $value;

        return $this;
    }

    public function getAction(): ?string
    {
        return $this->getMetadataValue('action');
    }

    public function getDebugInfo(): ?array
    {
        return $this->debugInfo;
    }

    public function getMessage(): string
    {
        return $this->message;
    }

    public function setMessage(string $message): self
    {
        $this->message = $this->sanitizeMessage($message);

        return $this;
    }

    public function getMetadata(): array
    {
        return $this->metadata;
    }

    // Utility methods

    public function setMetadata(array $metadata): self
    {
        $this->metadata = $metadata;

        return $this;
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function setType(string $type): self
    {
        $this->type = $type;

        return $this;
    }

    public function hasAction(): bool
    {
        return isset($this->metadata['action']);
    }

    public function isError(): bool
    {
        return $this->getMetadataValue('type') === 'error';
    }

    public function getMetadataValue(string $key, mixed $default = null): mixed
    {
        return $this->metadata[$key] ?? $default;
    }

    public function isSuccess(): bool
    {
        return $this->getMetadataValue('type') === 'success';
    }

    // Magic methods

    public function isValidationError(): bool
    {
        return $this->getMetadataValue('type') === 'validation_error';
    }

    public function prependMessage(string $message): self
    {
        $this->message = $message.$this->message;
        $this->message = $this->sanitizeMessage($this->message);

        return $this;
    }

    // Protected methods

    public function removeMetadata(string $key): self
    {
        unset($this->metadata[$key]);

        return $this;
    }

    // Factory methods for common responses

    public function toJson(): string
    {
        return json_encode($this->toArray(), JSON_UNESCAPED_UNICODE);
    }

    public function toArray(): array
    {
        return [
            'message' => $this->message,
            'type' => $this->type,
            'metadata' => $this->metadata,
            'formatted' => $this->formatForNetwork(),
            'is_continue' => $this->isContinue(),
            'is_end' => $this->isEnd(),
        ];
    }

    public function formatForNetwork(): string
    {
        return $this->type.' '.$this->message;
    }

    public function isContinue(): bool
    {
        return $this->type === self::CONTINUE;
    }

    public function isEnd(): bool
    {
        return $this->type === self::END;
    }

    public function truncateMessage(int $maxLength = 160): self
    {
        if (strlen($this->message) > $maxLength) {
            $this->message = substr($this->message, 0, $maxLength - 3).'...';
        }

        return $this;
    }
}
