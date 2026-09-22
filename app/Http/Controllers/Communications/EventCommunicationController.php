<?php

namespace App\Http\Controllers\Communications;

use App\Http\Controllers\Controller;
use App\Http\Requests\Communications\SendOutboundMessageRequest;
use App\Http\Requests\Communications\StoreReminderScheduleRequest;
use App\Models\Event;
use App\Models\MessageTemplate;
use App\Models\OutboundMessage;
use App\Services\CommunicationChannelRegistry;
use App\Services\CommunicationService;
use App\Services\ReminderService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

final class EventCommunicationController extends Controller
{
    public function index(Request $request, Event $event, CommunicationChannelRegistry $channels): View
    {
        Gate::authorize('viewAny', [OutboundMessage::class, $event]);
        $filters = $request->validate(['status' => ['nullable', 'in:pending,sent,failed'], 'channel' => ['nullable', 'in:email,sms,whatsapp']]);
        $messages = $event->outboundMessages()->with(['recipients', 'createdBy'])
            ->when($filters['status'] ?? null, fn ($query, $status) => $query->where('status', $status))
            ->when($filters['channel'] ?? null, fn ($query, $channel) => $query->where('channel', $channel))
            ->paginate(15)->withQueryString();

        return view('events.communications.index', [
            'event' => $event->load('client'), 'messages' => $messages, 'filters' => $filters,
            'templates' => MessageTemplate::query()->active()->orderBy('name')->get(),
            'availability' => $channels->availability(),
            'reminders' => $event->reminderSchedules()->latest('due_at')->limit(10)->get(),
            'organizationTimezone' => config('app.timezone'),
        ]);
    }

    public function store(SendOutboundMessageRequest $request, Event $event, CommunicationService $service): RedirectResponse
    {
        $message = $service->compose($event, $request->validated(), $request->user());

        return redirect()->route('events.communications.show', [$event, $message])
            ->with($message->status === 'sent' ? 'status' : 'warning', $message->status === 'sent' ? 'Message sent synchronously.' : 'Message preserved as failed. Configure the provider and retry.');
    }

    public function show(Request $request, Event $event, OutboundMessage $message): View
    {
        $this->nested($event, $message);
        Gate::authorize('view', $message);

        return view('events.communications.show', [
            'event' => $event,
            'message' => $message->load(['recipients.deliveryLogs.attemptedBy', 'createdBy', 'template']),
            'organizationTimezone' => config('app.timezone'),
        ]);
    }

    public function retry(Request $request, Event $event, OutboundMessage $message, CommunicationService $service): RedirectResponse
    {
        $this->nested($event, $message);
        Gate::authorize('retry', $message);
        $message = $service->retry($message, $request->user());

        return back()->with($message->status === 'sent' ? 'status' : 'warning', $message->status === 'sent' ? 'Message sent on retry.' : 'Retry failed; the sanitized attempt history was preserved.');
    }

    public function schedule(StoreReminderScheduleRequest $request, Event $event, ReminderService $reminders): RedirectResponse
    {
        $reminders->scheduleForUser($event, $request->date('due_at'), $request->user(), $request->user());

        return back()->with('status', 'Reminder scheduled. It will run through schedule:run or the manual command.');
    }

    private function nested(Event $event, OutboundMessage $message): void
    {
        abort_unless($message->event_id === $event->getKey(), 404);
    }
}
