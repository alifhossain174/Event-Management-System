<?php

namespace App\Http\Controllers\Bookings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Bookings\ConvertBookingRequest;
use App\Models\Booking;
use App\Models\EventCategory;
use App\Models\EventTemplate;
use App\Models\ModuleDefinition;
use App\Models\User;
use App\Services\BookingWorkflowService;
use App\Services\BranchScope;
use App\Services\EventModuleService;
use App\Services\SettingsService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

final class BookingConversionController extends Controller
{
    public function create(Request $request, Booking $booking, EventModuleService $modules, SettingsService $settings, BranchScope $branches): View|RedirectResponse
    {
        Gate::authorize('convert', $booking);
        if ($booking->event_id) {
            return redirect()->route('events.show', $booking->event_id)->with('status', 'This Booking has already been converted.');
        }

        $templates = EventTemplate::query()->where('status', 'active')->with('modules')->orderBy('name')->get();
        $template = $templates->firstWhere('id', $request->integer('template'))
            ?? $templates->firstWhere('event_category_id', $booking->requested_event_category_id);
        $plan = $modules->initializationPlan($template);

        return view('bookings.convert', [
            'booking' => $booking->load(['client', 'category']),
            'categories' => EventCategory::query()->where('is_active', true)->orderBy('name')->get(),
            'templates' => $templates,
            'selectedTemplate' => $template,
            'moduleDefinitions' => ModuleDefinition::query()->where('is_event_scoped', true)->where('is_active', true)->orderBy('sort_order')->get(),
            'initialEnabledModules' => $plan->enabledKeys(),
            'moduleWarnings' => $plan->warnings,
            'branches' => $branches->optionsFor($request->user()),
            'managers' => User::query()->where('is_active', true)->whereNull('deleted_at')->whereHas('roles', fn ($query) => $query->whereIn('slug', ['administrator', 'event-manager']))->orderBy('name')->get(),
            'timezones' => collect(config('system-settings.timezones', ['UTC']))->mapWithKeys(fn (string $timezone) => [$timezone => $timezone]),
            'organizationTimezone' => $settings->string('general.timezone'),
        ]);
    }

    public function store(ConvertBookingRequest $request, Booking $booking, BookingWorkflowService $workflow): RedirectResponse
    {
        $event = $workflow->convert($booking, $request->eventAttributes(), $request->enabledModules(), $request->user());

        return redirect()->route('events.show', $event)->with('status', 'Booking converted to a Draft Event. Repeating this action returns the same Event.');
    }
}
