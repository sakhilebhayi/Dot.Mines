<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Contracts\View\View;

/**
 * Signed one-click unsubscribe target for outbound notification emails.
 *
 * NotificationAlertMail has linked URL::signedRoute('email.unsubscribe')
 * since the notification pipeline shipped, but the route itself was never
 * registered -- rendering (not queuing) any alert email threw a
 * RouteNotFoundException, so every alert email would have failed at send
 * time. Found by the R9 audit's mail-render test.
 *
 * No auth middleware on purpose: the recipient clicks from their inbox and
 * may not have a session. The signature is the authorization.
 */
class EmailUnsubscribeController extends Controller
{
    /**
     * Maps the {type} route segment to the notification_preferences key it
     * switches off. Add new mail categories here alongside their mailable.
     */
    private const PREFERENCE_MAP = [
        'alert_notifications' => 'email_alerts',
        'report_notifications' => 'email_reports',
    ];

    public function __invoke(User $user, string $type): View
    {
        $preferenceKey = self::PREFERENCE_MAP[$type] ?? null;

        abort_if($preferenceKey === null, 404);

        $preferences = $user->notification_preferences ?? [];
        $preferences[$preferenceKey] = false;
        $user->update(['notification_preferences' => $preferences]);

        return view('emails.unsubscribed', [
            'type' => $type,
        ]);
    }
}
