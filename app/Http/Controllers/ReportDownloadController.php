<?php

namespace App\Http\Controllers;

use App\Models\Report;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportDownloadController
{
    public function download(Request $request, Report $report): StreamedResponse
    {
        // Accept both permanent and temporary (expiring) signed URLs.
        if (! $request->hasValidSignature() && ! $request->hasValidRelativeSignature()) {
            abort(403);
        }

        $user = Auth::user();
        if (! $user || $user->current_team_id !== $report->team_id) {
            abort(403);
        }

        if ($report->status !== 'completed') {
            abort(404);
        }

        // Prevent path traversal
        if (! $report->file_path || str_contains($report->file_path, '..')) {
            abort(404);
        }

        /** @var \Illuminate\Filesystem\FilesystemAdapter $disk */
        $disk = Storage::disk(config('reports.disk', 'local'));

        if (! $disk->exists($report->file_path)) {
            abort(404);
        }

        return $disk->download($report->file_path, $report->title.'.'.$report->format);
    }
}
