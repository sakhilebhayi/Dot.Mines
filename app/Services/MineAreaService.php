<?php

namespace App\Services;

use App\Models\MineArea;

class MineAreaService
{
    /**
     * Create a new mine area
     *
     * @param  array<string, mixed>  $data
     */
    public function create(int $teamId, array $data): MineArea
    {
        $data['team_id'] = $teamId;
        // Ensure legacy columns expected by current schema are populated when possible
        if (! array_key_exists('coordinates', $data)) {
            if (! empty($data['metadata']['boundary_coordinates'] ?? null)) {
                $data['coordinates'] = json_encode($data['metadata']['boundary_coordinates']);
            } else {
                $data['coordinates'] = json_encode([]);
            }
        }

        // Try to ensure center_latitude/center_longitude are populated to avoid DB NOT NULL issues.
        if (! array_key_exists('center_latitude', $data) || ! array_key_exists('center_longitude', $data)) {
            $centerLat = null;
            $centerLng = null;

            // Prefer explicit latitude/longitude fields
            if (array_key_exists('latitude', $data) && $data['latitude'] !== null) {
                $centerLat = $data['latitude'];
            }
            if (array_key_exists('longitude', $data) && $data['longitude'] !== null) {
                $centerLng = $data['longitude'];
            }

            // If not available, try to compute from coordinates (json string or array)
            if (($centerLat === null || $centerLng === null) && isset($data['coordinates'])) {
                $coords = $data['coordinates'];
                if (is_string($coords)) {
                    $decoded = json_decode($coords, true);
                    if (json_last_error() === JSON_ERROR_NONE) {
                        $coords = $decoded;
                    }
                }
                if (is_array($coords) && count($coords) > 0) {
                    $latSum = 0.0;
                    $lngSum = 0.0;
                    $count = 0;
                    foreach ($coords as $c) {
                        if (is_array($c) && isset($c['lat']) && isset($c['lng'])) {
                            $latSum += (float) $c['lat'];
                            $lngSum += (float) $c['lng'];
                            $count++;
                        }
                    }
                    if ($count > 0) {
                        $centerLat = $centerLat ?? ($latSum / $count);
                        $centerLng = $centerLng ?? ($lngSum / $count);
                    }
                }
            }

            // Final fallback to 0.0 to satisfy non-null DB columns
            if (! array_key_exists('center_latitude', $data)) {
                $data['center_latitude'] = $centerLat !== null ? $centerLat : 0.0;
            }
            if (! array_key_exists('center_longitude', $data)) {
                $data['center_longitude'] = $centerLng !== null ? $centerLng : 0.0;
            }
        }

        return MineArea::create($data);
    }

    /**
     * Update an existing mine area
     *
     * @param  array<string, mixed>  $data
     */
    public function update(MineArea $mineArea, array $data): MineArea
    {
        $mineArea->update($data);

        return $mineArea->refresh();
    }

    /**
     * Delete a mine area
     */
    public function delete(MineArea $mineArea): bool
    {
        return (bool) $mineArea->delete();
    }

    /**
     * Get mine area by ID with authorization check
     */
    public function getById(int $id, int $teamId): ?MineArea
    {
        return MineArea::forTeam($teamId)->find($id);
    }

    /**
     * Get statistics for a team's mine areas
     */
    /** @return array<string, mixed> */
    public function getTeamStatistics(int $teamId): array
    {
        $areas = MineArea::forTeam($teamId)->get();

        return [
            'total_areas' => $areas->count(),
            'active_areas' => $areas->where('status', 'active')->count(),
            'total_area_hectares' => $areas->sum('area_size_hectares') ?? 0,
            'areas_with_manager' => $areas->whereNotNull('manager_name')->count(),
        ];
    }
}
