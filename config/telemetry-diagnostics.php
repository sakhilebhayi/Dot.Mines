<?php

/*
 * Curated interpretations of manufacturer telemetry codes.
 *
 * Each entry maps an OEM code to the presentation a Dot.Mines user sees:
 *   'title'       -- short human issue name, e.g. 'Engine warning'
 *   'description' -- what it means, in plain language
 *   'action'      -- recommended action (rendered after the description)
 *   'priority'    -- critical | high | medium | low (static class of the
 *                    code; the machine's own reported severity, when the
 *                    API sends one, always outranks this)
 *   'component'   -- affected system, shown under technical details
 *   'type'        -- Alert::type bucket (engine, fuel, maintenance, ...)
 *
 * Add entries ONLY from documented Bell/OEM sources -- meanings are never
 * guessed. Codes without an entry render as a generic "Machine alert"
 * with the raw code preserved under technical details, so an unmapped
 * code is safe, just less specific.
 */
return [
    'bell' => [
        'codes' => [],
    ],
];
