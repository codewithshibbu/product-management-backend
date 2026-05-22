<?php

namespace App\Http\Controllers;

use App\Models\StockNotification;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    public function getList(Request $request)
    {
        $query = StockNotification::with('product:id,name');

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

    public function getUnreadCount()
    {
        return response()->json([
            'unread_count' => StockNotification::whereNull('read_at')->count(),
        ]);
    }

    public function markAsRead($notification_id)
    {
        $notification = StockNotification::find($notification_id);

        if (! $notification) {
            return response()->json(['message' => 'Record not found.'], 404);
        }

        if ($notification->read_at === null) {
            $notification->update(['read_at' => now()]);
        }

        return response()->json($notification->load('product:id,name'));
    }

    public function markAllAsRead()
    {
        StockNotification::whereNull('read_at')->update(['read_at' => now()]);

        return response()->json(['message' => 'All notifications marked as read.']);
    }
}
