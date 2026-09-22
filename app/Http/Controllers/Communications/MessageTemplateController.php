<?php

namespace App\Http\Controllers\Communications;

use App\Http\Controllers\Controller;
use App\Http\Requests\Communications\StoreMessageTemplateRequest;
use App\Models\MessageTemplate;
use App\Services\AuditService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

final class MessageTemplateController extends Controller
{
    public function index(Request $request): View
    {
        Gate::authorize('viewAny', MessageTemplate::class);
        $filters = $request->validate(['q' => ['nullable', 'string', 'max:100'], 'channel' => ['nullable', 'in:email,sms,whatsapp']]);
        $templates = MessageTemplate::query()
            ->when($filters['q'] ?? null, fn ($query, $q) => $query->where(fn ($query) => $query->where('name', 'like', "%{$q}%")->orWhere('key', 'like', "%{$q}%")))
            ->when($filters['channel'] ?? null, fn ($query, $channel) => $query->where('channel', $channel))
            ->orderBy('name')->paginate(15)->withQueryString();

        return view('communications.templates.index', compact('templates', 'filters'));
    }

    public function create(): View
    {
        Gate::authorize('create', MessageTemplate::class);

        return view('communications.templates.form', ['template' => new MessageTemplate, 'editing' => false]);
    }

    public function store(StoreMessageTemplateRequest $request, AuditService $audit): RedirectResponse
    {
        $template = MessageTemplate::query()->create($request->validated() + [
            'is_active' => $request->boolean('is_active'), 'created_by_user_id' => $request->user()->getKey(),
            'updated_by_user_id' => $request->user()->getKey(),
        ]);
        $audit->record('communication.template_created', $template, [], $template->only(['key', 'name', 'channel', 'category', 'is_active']), $request->user());

        return redirect()->route('message-templates.index')->with('status', 'Message template created.');
    }

    public function edit(MessageTemplate $messageTemplate): View
    {
        Gate::authorize('update', $messageTemplate);

        return view('communications.templates.form', ['template' => $messageTemplate, 'editing' => true]);
    }

    public function update(StoreMessageTemplateRequest $request, MessageTemplate $messageTemplate, AuditService $audit): RedirectResponse
    {
        $before = $messageTemplate->only(['key', 'name', 'channel', 'category', 'is_active']);
        $messageTemplate->update($request->validated() + [
            'is_active' => $request->boolean('is_active'), 'updated_by_user_id' => $request->user()->getKey(),
        ]);
        $audit->record('communication.template_updated', $messageTemplate, $before, $messageTemplate->only(array_keys($before)), $request->user());

        return redirect()->route('message-templates.index')->with('status', 'Message template updated.');
    }

    public function archive(Request $request, MessageTemplate $messageTemplate, AuditService $audit): RedirectResponse
    {
        Gate::authorize('update', $messageTemplate);
        $archiving = $messageTemplate->archived_at === null;
        $messageTemplate->update(['archived_at' => $archiving ? now() : null, 'is_active' => ! $archiving, 'updated_by_user_id' => $request->user()->getKey()]);
        $audit->record($archiving ? 'communication.template_archived' : 'communication.template_reactivated', $messageTemplate, [], ['archived' => $archiving], $request->user());

        return back()->with('status', $archiving ? 'Message template archived.' : 'Message template reactivated.');
    }
}
