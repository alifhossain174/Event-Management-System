<?php

namespace App\Http\Middleware;

use App\Models\Event;
use Closure;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\Response;

final class EnsureEventModuleEnabled
{
    public function handle(Request $request, Closure $next, ?string $moduleKey = null): Response|RedirectResponse
    {
        $event = $request->route('event');
        $moduleKey ??= $request->route('moduleKey');

        abort_unless($event instanceof Event && is_string($moduleKey), 404);
        abort_unless(in_array($moduleKey, config('event-modules.event_scoped_keys', []), true), 404);

        Gate::authorize('viewModule', [$event, $moduleKey]);

        $enabled = $event->moduleSettings()
            ->where('module_key', $moduleKey)
            ->where('is_enabled', true)
            ->exists();

        if (! $enabled) {
            if (Gate::allows('manageModules', $event)) {
                return redirect()
                    ->route('events.modules.edit', $event)
                    ->with('warning', 'That Event module is disabled. Enable it before opening its workspace. Preserved records were not exposed.');
            }

            abort(404);
        }

        return $next($request);
    }
}
