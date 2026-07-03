<?php

namespace App\Services;

use App\Models\BellEquipment;
use App\Models\BellEquipmentCautionCode;
use Illuminate\Support\Collection;

/**
 * MachineFaultCodeService
 *
 * Integration-agnostic aggregator for active machine fault codes.
 *
 * Sources (in order of addition):
 *
 *   1. bell_equipment_caution_codes — Bell ISO 15143-3 active caution/fault codes.
 *      Synced by BellIso15143Service on every snapshot cycle.
 *
 *   2. (future) Other OEM adapter tables — when a new manufacturer's integration
 *      is added, its fault codes should be appended here by implementing an
 *      OEM-specific resolver that returns the same standardised record shape.
 *
 * All callers (MaintenanceDashboard, MachineDetail, Reports, etc.) reference
 * only this service; they never query OEM-specific tables directly.
 *
 * Standardised fault code record:
 *   machine_id        (int|null)   — Platform machine ID (null if not yet linked)
 *   fault_code        (string)     — OEM-defined fault identifier
 *   fault_description (string|null)
 *   severity          (string)     — Critical | Warning | Caution | Info
 *   occurred_at       (string)     — Human-readable "X ago"
 *   source            (string)     — OEM name / integration label
 */
class MachineFaultCodeService
{
    /**
     * Return all active fault codes for the given machine IDs, merged from every
     * available OEM integration.
     *
     * @param  array<int>  $machineIds
     * @return Collection<int, array<string, mixed>>
     */
    public function getActiveFaultCodes(array $machineIds): Collection
    {
        if (empty($machineIds)) {
            return collect();
        }

        /** @var Collection<int, array<string, mixed>> $codes */
        $codes = collect();

        // ── Source 1: Bell Equipment caution codes ────────────────────────────
        $bellEquipmentMap = BellEquipment::whereIn('machine_id', $machineIds)
            ->pluck('equipment_key', 'machine_id')   // [machine_id => equipment_key]
            ->all();

        if (! empty($bellEquipmentMap)) {
            $bellCodes = BellEquipmentCautionCode::whereIn(
                'equipment_key',
                array_values($bellEquipmentMap)
            )
                ->where('is_active', true)
                ->orderByDesc('occurred_at')
                ->get()
                ->map(function (BellEquipmentCautionCode $code) use ($bellEquipmentMap): array {
                    // Reverse-lookup: equipment_key → machine_id
                    $machineId = array_search($code->equipment_key, $bellEquipmentMap, true);

                    return [
                        'machine_id' => $machineId !== false ? (int) $machineId : null,
                        'fault_code' => $code->fault_code,
                        'fault_description' => $code->fault_description,
                        'severity' => $code->severity ?? 'Info',
                        'occurred_at' => $code->occurred_at?->diffForHumans(),
                        'source' => 'Bell Equipment',
                    ];
                });

            foreach ($bellCodes as $item) {
                $codes->push($item);
            }
        }

        // ── Source 2 ... N: Additional OEM integrations ───────────────────────
        // To add a new OEM, append its fault codes using the same array shape:
        //
        //   $codes = $codes->merge($this->resolveVolvoFaultCodes($machineIds));
        //
        // No changes to callers (UI, reports, maintenance dashboard) are needed.

        return $codes->sortByDesc('occurred_at')->values();
    }
}
