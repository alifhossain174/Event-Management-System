<?php

namespace App\Http\Controllers\Notifications;

use App\Http\Controllers\Controller;
use App\Models\NotificationRecipient;
use App\Services\NotificationInboxService;
use App\Services\NotificationLinkService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

final class NotificationController extends Controller
{
    public function index(Request $request, NotificationInboxService $inbox, NotificationLinkService $links): View
    {
        Gate::authorize('viewAny', NotificationRecipient::class);
        $filters = $request->validate(['state' => ['nullable', Rule::in(['read', 'unread'])]]);

        return view('notifications.index', [
            'recipients' => $inbox->paginate($request->user(), $filters['state'] ?? null),
            'filters' => $filters,
            'links' => $links,
            'organizationTimezone' => config('app.timezone'),
        ]);
    }

    public function read(Request $request, NotificationRecipient $recipient, NotificationLinkService $links): RedirectResponse
    {
        Gate::authorize('update', $recipient);
        if (! $recipient->read_at) {
            $recipient->update(['read_at' => now()]);
        }

        $url = $links->url($recipient->notification);

        return $url ? redirect()->to($url) : back()->with('status', 'Notification marked as read.');
    }

    public function unread(Request $request, NotificationRecipient $recipient): RedirectResponse
    {
        Gate::authorize('update', $recipient);
        $recipient->update(['read_at' => null]);

        return back()->with('status', 'Notification marked as unread.');
    }

    public function readAll(Request $request): RedirectResponse
    {
        Gate::authorize('viewAny', NotificationRecipient::class);
        NotificationRecipient::query()->where('user_id', $request->user()->getKey())->whereNull('read_at')->update(['read_at' => now()]);

        return back()->with('status', 'All notifications marked as read.');
    }
}
