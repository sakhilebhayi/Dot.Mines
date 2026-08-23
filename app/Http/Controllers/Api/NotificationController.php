<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\NotificationResource;
use App\Models\Notification;
use App\Support\ApiPayload;
use App\Support\ApiResponse;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    /**
     * Get all notifications for team
     */
    public function index(Request $request): JsonResponse
    {
        $teamId = auth()->user()->current_team_id
            ?? (auth()->user()?->currentTeam ? auth()->user()?->currentTeam?->id : null);

        if (($teamId === null || $teamId === 0)) {
            return ApiResponse::collection([], NotificationResource::class);
        }

        $query = Notification::where('team_id', $teamId);

        if ($request->has('type')) {
            $query->where('type', $request->input('type'));
        }

        if ($request->has('alert_level')) {
            $query->whereIn('alert_level', (array) $request->input('alert_level'));
        }

        if ($request->input('unread_only') === 'true') {
            $query->where('is_read', false);
        }

        $notifications = $query->latest()->paginate(20);

        return ApiResponse::paginated($notifications, NotificationResource::class);
    }

    /**
     * Get unread notifications for user
     */
    /** @psalm-suppress PossiblyUnusedParam -- route signature keeps Request for consistency */
    public function unread(Request $request): JsonResponse
    {
        $userId = auth()->user()?->id;
        $teamId = auth()->user()->current_team_id
            ?? (auth()->user()?->currentTeam ? auth()->user()?->currentTeam?->id : null);

        $unread = Notification::where('team_id', $teamId)
            ->where('is_read', false)
            ->latest()
            ->paginate(20);

        return ApiResponse::paginated($unread, NotificationResource::class);
    }

    /**
     * Mark notification as read
     */
    /** @psalm-suppress PossiblyUnusedParam -- route signature keeps Request for consistency */
    public function markAsRead(Request $request, Notification $notification): JsonResponse
    {
        $this->authorize('view', $notification);

        $notification->update(['is_read' => true]);

        return response()->json(['message' => 'Marked as read']);
    }

    /**
     * Mark multiple as read
     */
    public function markMultipleAsRead(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'notification_ids' => 'required|array|min:1',
            'notification_ids.*' => 'exists:notifications,id',
        ]);

        $teamId = auth()->user()->current_team_id
            ?? (auth()->user()?->currentTeam ? auth()->user()?->currentTeam?->id : null);

        Notification::whereIn('id', $validated['notification_ids'])
            ->where('team_id', $teamId)
            ->update(['is_read' => true]);

        return response()->json(['message' => 'Notifications marked as read']);
    }

    /**
     * Get alert statistics
     */
    public function stats(Request $request): JsonResponse
    {
        $teamId = auth()->user()->current_team_id
            ?? (auth()->user()?->currentTeam ? auth()->user()?->currentTeam?->id : null);
        // start_date/end_date is the API-wide way to bound a time range;
        // days is kept as a shorthand for "the last N days".
        /** @psalm-suppress MixedAssignment */
        $daysRaw = $request->input('days');
        $days = is_numeric($daysRaw) ? (int) $daysRaw : 7;
        $fromDate = $request->filled('start_date')
            ? Carbon::parse(ApiPayload::str($request->input('start_date')))
            : now()->subDays($days);

        $query = Notification::where('team_id', $teamId)->where('created_at', '>=', $fromDate);

        if ($request->filled('end_date')) {
            $query->where('created_at', '<=', Carbon::parse(ApiPayload::str($request->input('end_date'))));
        }

        $alerts = $query->get();

        return response()->json([
            'total_notifications' => $alerts->count(),
            'unread_count' => $alerts->where('is_read', false)->count(),
            'by_alert_level' => [
                'critical' => $alerts->where('alert_level', 'critical')->count(),
                'high' => $alerts->where('alert_level', 'high')->count(),
                'warning' => $alerts->where('alert_level', 'warning')->count(),
                'info' => $alerts->where('alert_level', 'info')->count(),
            ],
            'by_type' => $alerts->groupBy('type')->map->count(),
            'by_time_period' => [
                'last_24h' => $alerts->where('created_at', '>=', now()->subDay())->count(),
                'last_7d' => $alerts->count(),
                'last_30d' => Notification::where('team_id', $teamId)
                    ->where('created_at', '>=', now()->subDays(30))
                    ->count(),
            ],
            'period_days' => $days,
        ]);
    }

    /**
     * Clear notifications
     */
    public function clear(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'type' => 'string|nullable',
            'alert_level' => 'string|nullable',
            'days_old' => 'integer|min:1|nullable',
        ]);

        $teamId = auth()->user()->current_team_id
            ?? (auth()->user()?->currentTeam ? auth()->user()?->currentTeam?->id : null);
        $query = Notification::where('team_id', $teamId);

        $type = $validated['type'] ?? null;
        if (is_string($type) && $type !== '') {
            $query->where('type', $type);
        }

        $alertLevel = $validated['alert_level'] ?? null;
        if (is_string($alertLevel) && $alertLevel !== '') {
            $query->where('alert_level', $alertLevel);
        }

        $daysOld = $validated['days_old'] ?? null;
        if (is_numeric($daysOld) && (int) $daysOld > 0) {
            $query->where('created_at', '<', now()->subDays((int) $daysOld));
        }

        /** @psalm-suppress MixedAssignment */
        $count = $query->delete();

        return response()->json([
            'message' => "{$count} notifications cleared",
            'count' => $count,
        ]);
    }
}
