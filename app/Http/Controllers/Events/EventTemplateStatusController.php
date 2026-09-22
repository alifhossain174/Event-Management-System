<?php

namespace App\Http\Controllers\Events;

use App\Http\Controllers\Controller;
use App\Http\Requests\Events\ChangeEventTemplateStatusRequest;
use App\Models\EventTemplate;
use App\Services\EventTemplateService;
use Illuminate\Http\RedirectResponse;

final class EventTemplateStatusController extends Controller
{
    public function __invoke(ChangeEventTemplateStatusRequest $request, EventTemplate $eventTemplate, EventTemplateService $service): RedirectResponse
    {
        $data = $request->validated();
        $service->changeStatus($eventTemplate, $data['action'], $request->user(), $data['reason'] ?? null);

        return back()->with('status', $data['action'] === 'archive' ? 'Template archived without changing existing Events.' : 'Template reactivated.');
    }
}
