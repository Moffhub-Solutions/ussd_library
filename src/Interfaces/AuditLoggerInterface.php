<?php

declare(strict_types=1);

namespace Moffhub\Ussd\Interfaces;

/**
 * Contract for USSD audit logging.
 *
 * Bind your own implementation to this interface in the container to replace
 * the default without forking.
 */
interface AuditLoggerInterface
{
    /**
     * Record an auditable action.
     *
     * @param  array<string, mixed>  $details
     */
    public function logAction(string $action, string $phoneNumber, array $details = [], string $level = 'info'): bool;

    /**
     * Record a security-relevant event (rate-limit hit, suspicious input, ...).
     *
     * @param  array<string, mixed>  $details
     */
    public function logSecurity(string $event, string $phoneNumber, array $details = [], string $level = 'warning'): bool;
}
