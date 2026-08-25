<?php

namespace App\Services\Guardian;

/**
 * Outcome of a single guardian health check.
 *
 * Status vocabulary is the cross-platform dot-guardian contract:
 * healthy < unknown < warning < critical (ascending badness). "unknown"
 * means the check could not produce a signal (no data yet, probe failed),
 * which must never masquerade as either healthy or broken.
 */
final class CheckResult
{
    public const HEALTHY = 'healthy';

    public const WARNING = 'warning';

    public const CRITICAL = 'critical';

    public const UNKNOWN = 'unknown';

    /** Ascending badness, used to aggregate an overall status. */
    private const SEVERITY_ORDER = [
        self::HEALTHY => 0,
        self::UNKNOWN => 1,
        self::WARNING => 2,
        self::CRITICAL => 3,
    ];

    /**
     * @param  array<string, mixed>  $metrics
     */
    private function __construct(
        private readonly string $status,
        private readonly string $message,
        private readonly array $metrics,
    ) {}

    /**
     * @param  array<string, mixed>  $metrics
     */
    public static function healthy(string $message = '', array $metrics = []): self
    {
        return new self(self::HEALTHY, $message, $metrics);
    }

    /**
     * @param  array<string, mixed>  $metrics
     */
    public static function warning(string $message = '', array $metrics = []): self
    {
        return new self(self::WARNING, $message, $metrics);
    }

    /**
     * @param  array<string, mixed>  $metrics
     */
    public static function critical(string $message = '', array $metrics = []): self
    {
        return new self(self::CRITICAL, $message, $metrics);
    }

    /**
     * @param  array<string, mixed>  $metrics
     */
    public static function unknown(string $message = '', array $metrics = []): self
    {
        return new self(self::UNKNOWN, $message, $metrics);
    }

    public function status(): string
    {
        return $this->status;
    }

    public function isWorseThan(self $other): bool
    {
        return self::SEVERITY_ORDER[$this->status] > self::SEVERITY_ORDER[$other->status];
    }

    /**
     * @return array{status: string, message: string, metrics: array<string, mixed>}
     */
    public function toArray(): array
    {
        return [
            'status' => $this->status,
            'message' => $this->message,
            'metrics' => $this->metrics,
        ];
    }
}
