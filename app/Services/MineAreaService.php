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
            /** @psalm-suppress MixedAssignment */
            $boundary = data_get($data, 'metadata.boundary_coordinates');
            $data['coordinates'] = json_encode(is_array($boundary) ? $boundary : []);
        }

        // Try to ensure center_latitude/center_longitude are populated to avoid DB NOT NULL issues.
        if (! array_key_exists('center_latitude', $data) || ! array_key_exists('center_longitude', $data)) {
            // Prefer explicit latitude/longitude fields
            $centerLat = isset($data['latitude']) && is_numeric($data['latitude']) ? (float) $data['latitude'] : null;
            $centerLng = isset($data['longitude']) && is_numeric($data['longitude']) ? (float) $data['longitude'] : null;

            // If not available, try to compute from coordinates (json string or array)
            if (($centerLat === null || $centerLng === null) && isset($data['coordinates'])) {
                /** @psalm-suppress MixedAssignment */
                $coords = $data['coordinates'];

                if (is_string($coords)) {
                    /** @psalm-suppress MixedAssignment */
                    $decoded = json_decode($coords, true);
                    $coords = is_array($decoded) ? $decoded : null;
                }

                if (is_array($coords) && $coords !== []) {
                    $latSum = 0.0;
                    $lngSum = 0.0;
                    $count = 0;

                    /** @psalm-suppress MixedAssignment */
                    foreach ($coords as $c) {
                        if (is_array($c) && is_numeric($c['lat'] ?? null) && is_numeric($c['lng'] ?? null)) {
                            $latSum += (float) $c['lat'];
                            $lngSum += (float) $c['lng'];
                            $count++;
                        }
                    }

                    if ($count > 0) {
                        $centerLat ??= $latSum / (float) $count;
                        $centerLng ??= $lngSum / (float) $count;
                    }
                }
            }

            // Final fallback to 0.0 to satisfy non-null DB columns
            if (! array_key_exists('center_latitude', $data)) {
                $data['center_latitude'] = $centerLat ?? 0.0;
            }
            if (! array_key_exists('center_longitude', $data)) {
                $data['center_longitude'] = $centerLng ?? 0.0;
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
            'total_area_hectares' => $areas->sum('area_size_hectares'),
            'areas_with_manager' => $areas->whereNotNull('manager_name')->count(),
        ];
    }
}
