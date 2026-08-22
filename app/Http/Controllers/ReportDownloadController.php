<?php

namespace App\Http\Controllers;

use App\Models\Report;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportDownloadController
{
    public function download(Request $request, Report $report): StreamedResponse
    {
        // Verify signed URL
        if (! $request->hasValidSignature()) {
            abort(403);
        }

        $user = Auth::user();
        if (! $user instanceof User || $user->current_team_id !== $report->team_id) {
            abort(403);
        }

        if ($report->status !== 'completed') {
            abort(404);
        }

        // Prevent path traversal
        if (! $report->file_path || str_contains($report->file_path, '..')) {
            abort(404);
        }

        if (! Storage::exists($report->file_path)) {
            abort(404);
        }

        return Storage::download($report->file_path, $report->title.'.'.$report->format);
    }
}
