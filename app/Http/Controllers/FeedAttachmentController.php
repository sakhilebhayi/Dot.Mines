<?php

namespace App\Http\Controllers;

use App\Models\FeedAttachment;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;

/**
 * Serves feed attachment files stored as binary blobs in the database.
 *
 * Route: GET /feed/attachments/{attachment}  (name: feed.attachment.serve)
 *
 * Security controls:
 *   - Authentication required (middleware on the route)
 *   - Team membership verified via the FeedPost policy
 *   - Legacy S3 records are redirected to their original URL
 *   - Content-Type set from DB-verified mime value (stored at upload time)
 *   - X-Content-Type-Options: nosniff to prevent MIME-sniffing attacks
 *   - Cache-Control: private to prevent CDN/proxy caching of private files
 *   - No user-supplied data is written to response headers unescaped
 */
class FeedAttachmentController extends Controller
{
    /**
     * Stream a feed attachment from database storage.
     */
    public function serve(Request $request, FeedAttachment $attachment): Response|RedirectResponse
    {
        // ── Authorization ────────────────────────────────────────────────────
        // Confirm the authenticated user belongs to the same team as the post.
        abort_unless(
            $attachment->post()->exists(),
            404
        );

        abort_unless(
            Gate::allows('view', $attachment->post),
            403,
            'You do not have permission to access this file.'
        );

        // ── Legacy S3 records ────────────────────────────────────────────────
        // Records uploaded before the DB-storage migration still have S3 URLs.
        // Redirect the browser to the original URL rather than proxying the byte stream.
        if ($attachment->storage_type === 's3') {
            abort_if(empty($attachment->file_url), 404, 'Legacy attachment URL is not available.');

            return redirect((string) $attachment->file_url);
        }

        // ── DB-stored records ────────────────────────────────────────────────
        abort_if(is_null($attachment->file_data), 404, 'Attachment data not found.');

        $fileData = (string) $attachment->file_data;

        // Determine appropriate Content-Disposition:
        // Images and audio are served inline (browser renders them);
        // everything else forces a download.
        $isInline = str_starts_with($attachment->file_type, 'image/')
                 || str_starts_with($attachment->file_type, 'audio/');

        // Escape the filename for use in the Content-Disposition header,
        // preventing header injection via crafted filenames.
        $safeFilename = addcslashes($attachment->file_name ?? 'attachment', '"\\');

        return response($fileData, 200, [
            'Content-Type' => $attachment->file_type,
            'Content-Length' => strlen($fileData),
            'Content-Disposition' => ($isInline ? 'inline' : 'attachment')
                                           .'; filename="'.$safeFilename.'"',
            'Cache-Control' => 'private, max-age=3600, must-revalidate',
            'X-Content-Type-Options' => 'nosniff',
            'X-Frame-Options' => 'SAMEORIGIN',
        ]);
    }
}
