<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Handles POPIA § 45(2) / CAN-SPAM compliant one-click email unsubscription.
 *
 * URLs are signed to prevent CSRF / unauthenticated mass-unsubscription.
 * The route is intentionally public (no auth middleware) so recipients can
 * unsubscribe without logging in.
 */
class EmailUnsubscribeController extends Controller
{
    /** Valid notification type values that may be targeted via email. */
    private const VALID_TYPES = [
        'shift_digest',
        'alert_notifications',
        'feed_onboarding',
        'maintenance_alerts',
        'report_notifications',
        'all_email',
    ];

    /**
     * Show the unsubscribe confirmation page.
     */
    public function show(Request $request): View|\Illuminate\Contracts\View\View
    {
        abort_unless($request->hasValidSignature(), 403);

        $user = User::findOrFail($request->query('user'));
        $type = $request->query('type', 'all_email');

        abort_unless(in_array($type, self::VALID_TYPES, strict: true), 422);

        return view('emails.unsubscribe', compact('user', 'type'));
    }

    /**
     * Process the unsubscription (POST from confirmation form).
     */
    public function handle(Request $request): RedirectResponse
    {
        abort_unless($request->hasValidSignature(), 403);

        $user = User::findOrFail($request->query('user'));
        $type = $request->query('type', 'all_email');

        abort_unless(in_array($type, self::VALID_TYPES, strict: true), 422);

        if ($type === 'all_email') {
            // Unsubscribe from every email-enabled notification preference for this user.
            $user->notificationPreferences()->update(['email_enabled' => false]);
        } else {
            // Upsert: create a row if it doesn't exist, set email_enabled = false.
            // We use team_id = 0 as a "global" override (no team scope).
            foreach ($user->teams as $team) {
                $user->notificationPreferences()->updateOrCreate(
                    [
                        'team_id' => $team->id,
                        'notification_type' => $type,
                    ],
                    ['email_enabled' => false]
                );
            }

            // If the user has no teams yet, create a global record (team_id = 0).
            if ($user->teams->isEmpty()) {
                $user->notificationPreferences()->updateOrCreate(
                    [
                        'team_id' => 0,
                        'notification_type' => $type,
                    ],
                    ['email_enabled' => false]
                );
            }
        }

        return redirect()->route('email.unsubscribed')->with('type', $type);
    }
}
