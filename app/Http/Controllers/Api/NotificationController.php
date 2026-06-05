<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Notification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class NotificationController extends Controller
{
    /**
     * Get all notifications for team
     */
    public function index(Request $request): mixed
    {
        $teamId = Auth::user()->current_team_id
            ?? (Auth::user()->currentTeam ? Auth::user()->currentTeam->id : null);

        if (! $teamId) {
            return response()->json(['data' => [], 'meta' => ['total' => 0]]);
        }

        $validated = $request->validate([
            'type' => 'nullable|string|max:100|alpha_dash',
            'alert_level' => 'nullable|array',
            'alert_level.*' => 'string|in:critical,high,warning,info',
            'unread_only' => 'nullable|boolean',
        ]);

        $query = Notification::where('team_id', $teamId);

        if (! empty($validated['type'])) {
            $query->where('type', $validated['type']);
        }

        if (! empty($validated['alert_level'])) {
            $query->whereIn('alert_level', $validated['alert_level']);
        }

        if ($request->boolean('unread_only')) {
            $query->where('is_read', false);
        }

        $notifications = $query->latest()->paginate(20);

        return response()->json([
            'data' => $notifications->items(),
            'meta' => [
                'current_page' => $notifications->currentPage(),
                'last_page' => $notifications->lastPage(),
                'total' => $notifications->total(),
                'per_page' => $notifications->perPage(),
            ],
        ]);
    }

    /**
     * Get unread notifications for user
     */
    public function unread(Request $request): mixed
    {
        $userId = Auth::user()->id;
        $teamId = Auth::user()->current_team_id
            ?? (Auth::user()->currentTeam ? Auth::user()->currentTeam->id : null);

        $unread = Notification::where('team_id', $teamId)
            ->where('is_read', false)
            ->latest()
            ->paginate(20);

        return response()->json([
            'data' => $unread->items(),
            'meta' => [
                'current_page' => $unread->currentPage(),
                'last_page' => $unread->lastPage(),
                'total' => $unread->total(),
                'per_page' => $unread->perPage(),
            ],
        ]);
    }

    /**
     * Mark notification as read
     */
    public function markAsRead(Request $request, Notification $notification): mixed
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

        $teamId = Auth::user()->current_team_id
            ?? (Auth::user()->currentTeam ? Auth::user()->currentTeam->id : null);

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
        $teamId = Auth::user()->current_team_id
            ?? (Auth::user()->currentTeam ? Auth::user()->currentTeam->id : null);
        $days = (int) $request->validate(['days' => 'nullable|integer|min:1|max:365'])['days'] ?? 7;
        $fromDate = now()->subDays($days);

        $alerts = Notification::where('team_id', $teamId)
            ->where('created_at', '>=', $fromDate)
            ->get();

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
            'type' => 'nullable|string|max:100|alpha_dash',
            'alert_level' => 'nullable|string|in:critical,high,warning,info',
            'days_old' => 'nullable|integer|min:1|max:365',
        ]);

        $teamId = Auth::user()->current_team_id
            ?? (Auth::user()->currentTeam ? Auth::user()->currentTeam->id : null);
        $query = Notification::where('team_id', $teamId);

        if ($validated['type'] ?? false) {
            $query->where('type', $validated['type']);
        }

        if ($validated['alert_level'] ?? false) {
            $query->where('alert_level', $validated['alert_level']);
        }

        if ($validated['days_old'] ?? false) {
            $query->where('created_at', '<', now()->subDays($validated['days_old']));
        }

        $count = $query->delete();

        return response()->json([
            'message' => "{$count} notifications cleared",
            'count' => $count,
        ]);
    }
}
