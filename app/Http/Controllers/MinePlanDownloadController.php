<?php

namespace App\Http\Controllers;

use App\Models\MinePlan;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class MinePlanDownloadController extends Controller
{
    /**
     * Serve a signed download for a mine plan file.
     * The route should be signed and authorized prior to calling this method.
     */
    /**
     * @param  int|string  $minePlanId
     */
    public function __invoke(Request $request, $minePlanId): RedirectResponse
    {
        if (! $request->hasValidSignature()) {
            abort(403);
        }

        // Expected query params: disk and path (or the MinePlan model can be looked up)
        $diskParam = $request->query('disk');
        $disk = is_string($diskParam) && $diskParam !== '' ? $diskParam : (string) config('filesystems.default');
        $path = $request->query('path');

        if (! is_string($path) || $path === '') {
            abort(404);
        }

        // Normalize and validate path: disallow traversal and require allowed prefix
        $normalized = str_replace('\\', '/', $path);
        if (Str::contains($normalized, '..')) {
            abort(400, 'Invalid path');
        }

        // Restrict downloads to the mine-plans directory to reduce risk of exposing other files.
        $allowedPrefix = 'mine-plans/';
        if (! Str::startsWith($normalized, $allowedPrefix)) {
            abort(403, 'Forbidden');
        }

        // Optional model-level authorization: if a MinePlan model exists, verify ownership
        if (class_exists(MinePlan::class)) {
            $minePlan = MinePlan::find($minePlanId);
            if (! $minePlan || $minePlan->team_id !== auth()->user()->current_team_id) {
                abort(403);
            }
        }

        // Serve via Storage APIs to avoid raw stream passthroughs and to support S3.
        if (! Storage::disk($disk)->exists($normalized)) {
            abort(404);
        }

        // For S3, redirect to a short-lived temporary URL; for local/private disks, use Storage::download
        $mime = Storage::disk($disk)->mimeType($normalized) ?? 'application/octet-stream';
        $filename = basename($normalized);

        $securityHeaders = [
            'Content-Security-Policy' => "default-src 'none';",
            'X-Content-Type-Options' => 'nosniff',
        ];

        if ($disk === 's3') {
            $url = Storage::disk('s3')->temporaryUrl($normalized, now()->addMinutes(15));

            return redirect()->away($url);
        }

        return Storage::disk($disk)->download($normalized, $filename, array_merge($securityHeaders, [
            'Content-Type' => $mime,
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
        ]));
    }
}
