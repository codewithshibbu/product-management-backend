<?php

namespace App\Http\Controllers;

use App\Models\StockNotification;
use App\Support\SuperAdmin;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    public function getList(Request $request)
    {
        $query = StockNotification::with(['product:id,name,user_id', 'product.user:id,name']);

        if ($request->boolean('unread_only')) {
            $query->whereNull('read_at');
        }

        $rows = (int) $request->query('rows', 20);
        if ($rows < 1) {
            $rows = 20;
        }

        $notifications = $query
            ->orderByDesc('created_at')
            ->paginate($rows);

        return response()->json($notifications);
    }

    public function getUnreadCount(Request $request)
    {
        return response()->json([
            'unread_count' => StockNotification::whereNull('read_at')->count(),
        ]);
    }

    public function markAsRead(Request $request, $notification_id)
    {
        $notification = StockNotification::with('product:id,user_id')->find($notification_id);

        if (! $notification) {
            return response()->json(['message' => 'Record not found.'], 404);
        }

        if (! $this->canManageNotification($request, $notification)) {
            return response()->json(['message' => 'You can only mark your own product alerts as read.'], 403);
        }

        if ($notification->read_at === null) {
            $notification->update(['read_at' => now()]);
        }

        return response()->json($notification->load(['product:id,name,user_id', 'product.user:id,name']));
    }

    public function markAllAsRead(Request $request)
    {
        $query = StockNotification::whereNull('read_at');

        if (! SuperAdmin::check($request->user())) {
            $query->whereHas('product', fn ($q) => $q->where('user_id', $request->user()->id));
        }

        $count = $query->update(['read_at' => now()]);

        return response()->json([
            'message' => "{$count} notification(s) marked as read.",
            'updated_count' => $count,
        ]);
    }

    public function deleteAll(Request $request)
    {
        if (! SuperAdmin::check($request->user())) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        $count = StockNotification::count();
        StockNotification::query()->delete();

        return response()->json([
            'message' => "{$count} notification(s) cleared.",
            'deleted_count' => $count,
        ]);
    }

    private function canManageNotification(Request $request, StockNotification $notification): bool
    {
        if (SuperAdmin::check($request->user())) {
            return true;
        }

        return $notification->product && $notification->product->user_id === $request->user()->id;
    }
}
