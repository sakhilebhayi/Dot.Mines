<?php

namespace App\Http\Controllers;

use App\Jobs\DeleteUserDataJob;
use App\Jobs\ExportUserDataJob;
use App\Models\GdprRequest;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

class GdprController extends Controller
{
    /**
     * Submit a data export request.
     */
    public function requestExport(Request $request): RedirectResponse
    {
        /** @var User $user */
        $user = Auth::user();

        // Prevent duplicate pending requests
        $existing = GdprRequest::where('user_id', $user->id)
            ->where('type', GdprRequest::TYPE_EXPORT)
            ->whereIn('status', [GdprRequest::STATUS_PENDING, GdprRequest::STATUS_PROCESSING])
            ->exists();

        if ($existing) {
            return back()->with('status', 'gdpr-export-pending');
        }

        $gdprRequest = GdprRequest::create([
            'user_id' => $user->id,
            'type' => GdprRequest::TYPE_EXPORT,
            'status' => GdprRequest::STATUS_PENDING,
            'email' => $user->email,
        ]);

        ExportUserDataJob::dispatch($gdprRequest)->onQueue('default');

        return back()->with('status', 'gdpr-export-requested');
    }

    /**
     * Download the completed data export.
     */
    public function downloadExport(Request $request, string $token): Response|RedirectResponse
    {
        $gdprRequest = GdprRequest::where('download_token', $token)
            ->where('user_id', Auth::id())
            ->where('status', GdprRequest::STATUS_COMPLETED)
            ->firstOrFail();

        if ($gdprRequest->isExpired()) {
            return redirect()->route('profile.show')
                ->with('status', 'gdpr-export-expired');
        }

        if ($gdprRequest->file_path === null || ! Storage::disk('local')->exists($gdprRequest->file_path)) {
            abort(404, 'Export file not found.');
        }

        $fileContents = Storage::disk('local')->get($gdprRequest->file_path) ?? '';

        return new Response($fileContents, 200, [
            'Content-Type' => 'application/json',
            'Content-Disposition' => 'attachment; filename="my-data-export.json"',
        ]);
    }

    /**
     * Submit an account deletion request.
     */
    public function requestDeletion(Request $request): RedirectResponse
    {
        $request->validate([
            'confirmation' => 'required|in:DELETE',
        ]);

        /** @var User $user */
        $user = Auth::user();

        $existing = GdprRequest::where('user_id', $user->id)
            ->where('type', GdprRequest::TYPE_DELETE)
            ->whereIn('status', [GdprRequest::STATUS_PENDING, GdprRequest::STATUS_PROCESSING])
            ->exists();

        if ($existing) {
            return back()->with('status', 'gdpr-delete-pending');
        }

        $gdprRequest = GdprRequest::create([
            'user_id' => $user->id,
            'type' => GdprRequest::TYPE_DELETE,
            'status' => GdprRequest::STATUS_PENDING,
            'email' => $user->email,
            'reason' => $request->input('reason'),
        ]);

        DeleteUserDataJob::dispatch($gdprRequest)->onQueue('default');

        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/')->with('status', 'gdpr-delete-requested');
    }

    /**
     * Show the user's GDPR request history.
     */
    public function index(): View
    {
        $requests = GdprRequest::where('user_id', Auth::id())
            ->orderByDesc('created_at')
            ->get();

        return view('gdpr.index', compact('requests'));
    }
}
