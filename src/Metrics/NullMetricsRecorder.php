<?php

declare(strict_types=1);

namespace Moffhub\Ussd\Metrics;

use Moffhub\Ussd\Interfaces\MetricsRecorderInterface;

/**
 * Default no-op metrics recorder. Bind your own MetricsRecorderInterface in the
 * container to actually record metrics.
 */
class NullMetricsRecorder implements MetricsRecorderInterface
{
    public function menuEntered(string $menu): void {}

    public function menuCompleted(string $menu): void {}

    public function sessionAbandoned(string $menu): void {}

    public function dwell(string $menu, float $seconds): void {}
}
