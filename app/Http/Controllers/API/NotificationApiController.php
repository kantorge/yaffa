<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Routing\Attributes\Controllers\Middleware;

#[Middleware('auth:sanctum')]
#[Middleware('verified')]
#[Middleware('abilities:read', only: ['index'])]
#[Middleware('abilities:write', only: ['markRead', 'markAllRead'])]
class NotificationApiController extends Controller
{
    /**
     * List notifications
     *
     * Polling endpoint: returns the user's newest notifications, optionally only those created after `since`
     * (ISO 8601) or only unread ones. The payload holds a type, an entity id and a generic title, nothing sensitive.
     */
    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'since' => ['nullable', 'date'],
            'unread' => ['nullable', 'boolean'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        /** @var User $user */
        $user = $request->user();

        $query = $user->notifications()->latest();

        if ($request->filled('since')) {
            $query->where('created_at', '>', Carbon::parse((string) $request->input('since')));
        }

        if ($request->boolean('unread')) {
            $query->whereNull('read_at');
        }

        $notifications = $query->limit((int) $request->input('limit', 50))->get()
            ->map(fn (DatabaseNotification $n) => [
                'id' => $n->id,
                'type' => $n->data['type'] ?? null,
                'entity_type' => $n->data['entity_type'] ?? null,
                'entity_id' => $n->data['entity_id'] ?? null,
                'title' => $n->data['title'] ?? null,
                'read_at' => $n->read_at,
                'created_at' => $n->created_at,
            ]);

        return response()->json(['data' => $notifications]);
    }

    /**
     * Mark a notification as read
     */
    public function markRead(Request $request, string $id): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $user->notifications()->whereKey($id)->firstOrFail()->markAsRead();

        return response()->json(null, 204);
    }

    /**
     * Mark all notifications as read
     */
    public function markAllRead(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $user->unreadNotifications()->update(['read_at' => now()]);

        return response()->json(null, 204);
    }
}
