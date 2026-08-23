<?php

namespace App\Http\Resources;

use App\Models\MineArea;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A mine area, as referenced from a machine, tank, or geofence.
 *
 * @mixin MineArea
 */
class MineAreaSummaryResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    #[\Override]
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'status' => $this->status,
        ];
    }
}
