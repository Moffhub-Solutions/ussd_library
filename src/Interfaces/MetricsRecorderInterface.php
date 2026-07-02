<?php

declare(strict_types=1);

namespace Moffhub\Ussd\Interfaces;

/**
 * Sink for operational USSD metrics (Prometheus counters, StatsD, etc.).
 *
 * Bind an implementation to this interface in the container to receive funnel
 * metrics. Everything is keyed by menu (never by phone number or session id) to
 * keep metric cardinality bounded. The default is a no-op.
 */
interface MetricsRecorderInterface
{
    /**
     * A menu was entered / processed for a request.
     */
    public function menuEntered(string $menu): void;

    /**
     * A session completed normally (terminal END) at this menu.
     */
    public function menuCompleted(string $menu): void;

    /**
     * A session was abandoned (expired without completing) at this menu.
     * Typically emitted by the session sweeper, not inline.
     */
    public function sessionAbandoned(string $menu): void;

    /**
     * Time spent handling a step at this menu, in seconds.
     */
    public function dwell(string $menu, float $seconds): void;
}
