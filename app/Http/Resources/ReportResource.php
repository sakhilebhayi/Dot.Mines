<?php

namespace App\Http\Resources;

use App\Models\Report;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A generated report.
 *
 * `file_path` is deliberately absent: it is an internal storage location
 * (disk-relative, sometimes bucket-qualified) that leaked into every report
 * listing. Consumers fetch the file through GET /api/reports/{id}/download,
 * which authorizes and streams it.
 *
 * @mixin Report
 */
class ReportResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    #[\Override]
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'type' => $this->type,
            'status' => $this->status,
            'format' => $this->format,
            'file_size' => $this->file_size,
            'filters' => $this->filters,
            'error_message' => $this->error_message,

            'generated_by' => $this->generated_by,
            'generated_at' => $this->generated_at?->toIso8601String(),
            'expires_at' => $this->expires_at?->toIso8601String(),

            'created_at' => $this->created_at->toIso8601String(),
            'updated_at' => $this->updated_at->toIso8601String(),

            'generated_by_user' => UserSummaryResource::make($this->whenLoaded('generatedBy')),
        ];
    }
}
