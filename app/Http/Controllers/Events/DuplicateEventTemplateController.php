<?php

namespace App\Http\Controllers\Events;

use App\Http\Controllers\Controller;
use App\Models\EventTemplate;
use App\Services\EventTemplateService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

final class DuplicateEventTemplateController extends Controller
{
    public function __invoke(Request $request, EventTemplate $eventTemplate, EventTemplateService $service): RedirectResponse
    {
        Gate::authorize('duplicate', $eventTemplate);
        $copy = $service->duplicate($eventTemplate, $request->user());

        return redirect()->route('settings.event-templates.edit', $copy)->with('status', 'Template duplicated. Review the editable copy before use.');
    }
}
