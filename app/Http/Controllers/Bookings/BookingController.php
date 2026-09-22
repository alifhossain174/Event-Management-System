<?php

namespace App\Http\Controllers\Bookings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Bookings\StoreBookingRequest;
use App\Models\Booking;
use App\Models\Client;
use App\Models\EventCategory;
use App\Services\BookingQueryService;
use App\Services\BookingWorkflowService;
use App\Services\BranchScope;
use App\Services\SettingsService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

final class BookingController extends Controller
{
    public function index(Request $request, BookingQueryService $queries, BranchScope $branches, SettingsService $settings): View
    {
        Gate::authorize('viewAny', Booking::class);
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:255'],
            'status' => ['nullable', 'in:'.implode(',', Booking::STATUSES)],
            'category' => ['nullable', 'integer', 'exists:event_categories,id'],
            'client' => ['nullable', 'integer', 'exists:clients,id'],
            'branch' => ['nullable', 'integer', 'exists:branches,id'],
            'date_from' => ['nullable', 'date_format:Y-m-d'],
            'date_to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:date_from'],
        ]);

        $bookings = $queries->applyDashboardFilters($queries->visibleTo($request->user()), $filters)
            ->with(['client:id,display_name', 'category:id,name', 'branch:id,name', 'event:id,reference_number,name'])
            ->search($filters['q'] ?? null)
            ->when($filters['status'] ?? null, fn ($query, $status) => $query->where('status', $status))
            ->when($filters['client'] ?? null, fn ($query, $client) => $query->where('client_id', $client))
            ->orderByDesc('requested_starts_at')
            ->paginate(20)
            ->withQueryString();

        return view('bookings.index', [
            'bookings' => $bookings,
            'filters' => $filters,
            'categories' => EventCategory::query()->orderBy('name')->get(),
            'clients' => $branches->apply(Client::query()->where('status', 'active'), $request->user())->orderBy('display_name')->limit(200)->get(),
            'branches' => $branches->optionsFor($request->user()),
            'organizationTimezone' => $settings->string('general.timezone'),
        ]);
    }

    public function create(Request $request, SettingsService $settings, BranchScope $branches): View
    {
        Gate::authorize('create', Booking::class);

        return view('bookings.create', [
            'clients' => $branches->apply(Client::query()->where('status', 'active'), $request->user())->orderBy('display_name')->limit(500)->get(),
            'categories' => EventCategory::query()->where('is_active', true)->orderBy('sort_order')->orderBy('name')->get(),
            'branches' => $branches->optionsFor($request->user()),
            'timezones' => collect(config('system-settings.timezones', ['UTC']))->mapWithKeys(fn (string $timezone) => [$timezone => $timezone]),
            'organizationTimezone' => $settings->string('general.timezone'),
        ]);
    }

    public function store(StoreBookingRequest $request, BookingWorkflowService $workflow): RedirectResponse
    {
        $booking = $workflow->create($request->bookingAttributes(), $request->user());

        return redirect()->route('bookings.show', $booking)->with('status', 'Booking enquiry created. Event creation remains available independently.');
    }

    public function show(Booking $booking, SettingsService $settings): View
    {
        Gate::authorize('view', $booking);
        $booking->load([
            'client', 'category', 'branch', 'event', 'statusHistory.actor', 'changes.actor',
            'waitlistEntry.addedBy', 'waitlistEntry.promotedBy', 'documentLinks.document.currentVersion',
        ]);

        return view('bookings.show', [
            'booking' => $booking,
            'organizationTimezone' => $settings->string('general.timezone'),
            'timezones' => collect(config('system-settings.timezones', ['UTC']))->mapWithKeys(fn (string $timezone) => [$timezone => $timezone]),
        ]);
    }
}
