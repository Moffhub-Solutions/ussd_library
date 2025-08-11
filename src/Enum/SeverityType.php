<?php

declare(strict_types=1);

namespace Moffhub\Ussd\Enum;

enum SeverityType: string
{
    case Low = 'low';
    case Medium = 'medium';
    case High = 'high';

    public function display(): string
    {
        return match ($this) {
            self::Low => 'Low Severity',
            self::Medium => 'Medium Severity',
            self::High => 'High Severity',
        };
    }
}
