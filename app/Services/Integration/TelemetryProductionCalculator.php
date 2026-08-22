<?php

namespace App\Services\Integration;

use Illuminate\Support\Carbon;

/**
 * Pure math for turning cumulative telemetry counters into per-day
 * production figures (refactor program R2: extracted from
 * IntegrationService so the domain arithmetic is testable in isolation
 * and readable without wading through sync orchestration). No queries,
 * no side effects: readings in, deltas out.
 */
final class TelemetryProductionCalculator
{
    /**
     * Bucket raw cumulative readings into calendar days of the given
     * timezone, keeping each day's first and last reading.
     *
     * @param  list<array{timestamp?: string|null, value?: mixed, units?: string|null}>  $readings
     * @return array<string, array{first_ts: string, last_ts: string, first_value: float, last_value: float, units: ?string}>
     */
    public function groupCumulativeReadingsByDay(array $readings, string $timezone, Carbon $start, Carbon $end): array
    {
        $days = [];

        foreach ($readings as $reading) {
            $rawTimestamp = $reading['timestamp'] ?? null;
            $value = $reading['value'] ?? null;

            if ($rawTimestamp === null || $rawTimestamp === '' || ! is_numeric($value)) {
                continue;
            }

            try {
                $timestamp = Carbon::parse($rawTimestamp);
            } catch (\Throwable) {
                continue;
            }

            if ($timestamp->lt($start) || $timestamp->gt($end)) {
                continue;
            }

            $local = $timestamp->clone()->setTimezone($timezone);

            // A cumulative snapshot stamped exactly at local midnight closes
            // the day that just ENDED, it doesn't open the new one -- Bell
            // emits its daily counters at 22:00Z, which is 00:00 SAST, so
            // without this rule every day's production books one day late
            // (the brief's "a cycle completed at 23:59 must not appear in
            // the following day" boundary case, in reverse).
            if ($local->format('H:i:s') === '00:00:00') {
                $local = $local->subSecond();
            }

            $date = $local->toDateString();
            $value = (float) $value;

            if (! isset($days[$date])) {
                $days[$date] = [
                    'first_ts' => $rawTimestamp,
                    'last_ts' => $rawTimestamp,
                    'first_value' => $value,
                    'last_value' => $value,
                    'units' => $reading['units'] ?? null,
                ];
            } else {
                $days[$date]['last_ts'] = $rawTimestamp;
                $days[$date]['last_value'] = $value;
                $days[$date]['units'] = $days[$date]['units'] ?? ($reading['units'] ?? null);
            }
        }

        ksort($days);

        return $days;
    }

    /**
     * Convert day buckets of a cumulative counter into per-day deltas.
     * Each day's baseline is the previous day's closing value ($carried
     * baseline for the window's first day, recovered from the last synced
     * record so re-syncs stay consistent with what was already stored);
     * a day with no earlier baseline at all falls back to its own first
     * reading, which can only ever under-count -- never invent
     * production. Negative deltas (counter reset after an ECU/machine
     * swap) clamp to zero for the same reason.
     *
     * @param  array<string, array{first_ts: string, last_ts: string, first_value: float, last_value: float, units: ?string}>  $days
     * @return array<string, array{delta: float, first_reading_utc: string, last_reading_utc: string, end_value: float, units: ?string}>
     */
    public function dailyDeltas(array $days, ?float $carriedBaseline): array
    {
        $deltas = [];
        $previousEnd = $carriedBaseline;

        foreach ($days as $date => $day) {
            $baseline = $previousEnd ?? $day['first_value'];

            $deltas[$date] = [
                'delta' => max(0.0, $day['last_value'] - $baseline),
                'first_reading_utc' => $day['first_ts'],
                'last_reading_utc' => $day['last_ts'],
                'end_value' => $day['last_value'],
                'units' => $day['units'],
            ];

            $previousEnd = $day['last_value'];
        }

        return $deltas;
    }

    /**
     * Bell's ISO 15143-3 reference data reports payload in kilograms; the
     * per-reading units attribute is honoured when the feed carries one.
     */
    public function payloadToTonnes(?float $value, ?string $units): float
    {
        if ($value === null) {
            return 0.0;
        }

        $normalised = strtolower(trim($units ?? 'kilogram'));

        return match (true) {
            in_array($normalised, ['kilogram', 'kilograms', 'kg'], true) => $value / 1000.0,
            in_array($normalised, ['tonne', 'tonnes', 't', 'metric ton', 'metricton'], true) => $value,
            in_array($normalised, ['pound', 'pounds', 'lb', 'lbs'], true) => $value * 0.00045359237,
            default => $value / 1000.0,
        };
    }
}
