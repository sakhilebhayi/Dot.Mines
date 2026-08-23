<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\OperatorDocument;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;

/**
 * The only way an operator document leaves storage.
 *
 * The files live on a private disk with no public URL, so every download
 * passes through here: team scope via route binding (the global scope turns
 * another team's id into a 404), the operator-view policy, the medical
 * permission when the document is a medical, and an activity-log entry --
 * the audit trail the brief requires. Served as a download, never rendered
 * inline, so a crafted HTML/SVG upload cannot execute in the app's origin.
 */
class OperatorDocumentController extends Controller
{
    use AuthorizesRequests;

    public function __invoke(OperatorDocument $document): Response
    {
        $operator = $document->operator;

        if ($operator === null) {
            abort(404);
        }

        $this->authorize('view', $operator);

        if ($document->isMedical()) {
            $this->authorize('viewMedical', $operator);
        }

        abort_unless(Storage::disk($document->disk)->exists($document->path), 404);

        ActivityLog::create([
            'team_id' => $document->team_id,
            'user_id' => auth()->id(),
            'action' => 'operator_document_downloaded',
            'description' => 'Downloaded "'.$document->title.'" ('.$document->original_name
                .') for operator '.$operator->name,
        ]);

        return Storage::disk($document->disk)->download(
            $document->path,
            $document->original_name,
            ['Content-Type' => $document->mime_type, 'X-Content-Type-Options' => 'nosniff'],
        );
    }
}
