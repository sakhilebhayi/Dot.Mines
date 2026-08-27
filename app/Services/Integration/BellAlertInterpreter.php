<?php

namespace App\Services\Integration;

use App\Support\ApiPayload;

/**
 * Translates raw Bell/ISO 15143-3 telemetry codes into what a Dot.Mines
 * user should read. A mining supervisor cannot be expected to know what
 * "Bell caution code E204" means -- and the raw OEM string is jargon, not
 * a diagnosis.
 *
 * Interpretation sources, in order:
 *
 *   1. Bell's own spec fields when the API provides them (CodeDescription,
 *      CodeSeverity, CodeSource per ISO 15143-3 / AEMP 2.0) -- real
 *      manufacturer data, used verbatim, never invented.
 *   2. The curated catalog (config/telemetry-diagnostics.php) for codes
 *      whose meaning has been documented. Entries are added as Bell
 *      documentation is obtained; meanings are never guessed.
 *   3. A graceful generic -- "Machine alert" plus a plain-language call to
 *      action -- with the raw code preserved under technical details for
 *      technicians. Raw ISO terminology is never the primary message.
 *
 * The machine's own reported severity always outranks the catalog's
 * static priority: the catalog describes the code class, the machine
 * reports how bad it is right now.
 */
final class BellAlertInterpreter
{
    private const SEVERITY_MAP = [
        'critical' => 'critical',
        'severe' => 'critical',
        'high' => 'high',
        'medium' => 'medium',
        'moderate' => 'medium',
        'low' => 'low',
        'info' => 'low',
        'informational' => 'low',
    ];

    /**
     * @param  array<string, array<string, string>>  $catalog  code => presentation
     */
    public function __construct(private readonly array $catalog = []) {}

    public static function fromConfig(): self
    {
        /** @var array<string, array<string, string>> $catalog */
        $catalog = config('telemetry-diagnostics.bell.codes', []);

        return new self($catalog);
    }

    /**
     * @param  array<string, string>  $attributes  raw attributes from the telemetry reading
     * @return array{title: string, description: string, priority: string, type: string, technical: array<string, string>}
     */
    public function interpret(string $code, array $attributes): array
    {
        $oemDescription = $this->attribute($attributes, ['CodeDescription', 'Description']);
        $oemSeverity = $this->attribute($attributes, ['CodeSeverity', 'Severity']);
        $oemComponent = $this->attribute($attributes, ['CodeSource', 'Source', 'Component']);

        $technical = ['code' => $code, 'source' => 'Bell ISO 15143-3 telemetry'];

        if ($oemSeverity !== null) {
            $technical['severity_raw'] = $oemSeverity;
        }

        if ($oemComponent !== null) {
            $technical['component'] = $oemComponent;
        }

        $entry = $this->catalog[$code] ?? null;

        if ($oemDescription !== null) {
            // The manufacturer said what it means -- use it verbatim.
            $sentences = [rtrim($oemDescription, '.').'.'];

            if ($oemComponent !== null) {
                $sentences[] = "Affected system: {$oemComponent}.";
            }

            $sentences[] = 'Inspect the machine and address the reported condition before continuing operation.';

            return [
                'title' => $oemDescription,
                'description' => implode(' ', $sentences),
                'priority' => $this->priority($oemSeverity, ApiPayload::str($entry['priority'] ?? '', 'medium')),
                'type' => ApiPayload::str($entry['type'] ?? '', 'sensor'),
                'technical' => $technical,
            ];
        }

        if ($entry !== null) {
            $description = rtrim(ApiPayload::str($entry['description'] ?? '', ''), '.').'.';

            if (($entry['action'] ?? '') !== '') {
                $description .= ' Recommended action: '.rtrim(ApiPayload::str($entry['action'], ''), '.').'.';
            }

            if (($entry['component'] ?? null) !== null) {
                $technical['component'] = ApiPayload::str($entry['component'], '');
            }

            return [
                'title' => ApiPayload::str($entry['title'] ?? '', 'Machine alert'),
                'description' => $description,
                'priority' => $this->priority($oemSeverity, ApiPayload::str($entry['priority'] ?? '', 'medium')),
                'type' => ApiPayload::str($entry['type'] ?? '', 'sensor'),
                'technical' => $technical,
            ];
        }

        // Unknown code: say something honest and useful, never raw jargon.
        return [
            'title' => 'Machine alert',
            'description' => 'The machine has reported an alert that requires attention. '
                .'Inspect the machine; the manufacturer code is available under technical details.',
            'priority' => $this->priority($oemSeverity, 'medium'),
            'type' => 'sensor',
            'technical' => $technical,
        ];
    }

    /**
     * @param  list<string>  $names
     * @param  array<string, string>  $attributes
     */
    private function attribute(array $attributes, array $names): ?string
    {
        $lowered = array_change_key_case($attributes, CASE_LOWER);

        foreach ($names as $name) {
            $value = trim(ApiPayload::str($lowered[strtolower($name)] ?? '', ''));

            if ($value !== '') {
                return $value;
            }
        }

        return null;
    }

    private function priority(?string $oemSeverity, string $fallback): string
    {
        if ($oemSeverity !== null) {
            return self::SEVERITY_MAP[strtolower($oemSeverity)] ?? 'medium';
        }

        return in_array($fallback, ['critical', 'high', 'medium', 'low'], true) ? $fallback : 'medium';
    }
}
