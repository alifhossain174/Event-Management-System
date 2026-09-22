<?php

namespace App\Http\Controllers\Registrations;

use App\Http\Controllers\Controller;
use App\Http\Requests\Registrations\RegistrationSubmissionRequest;
use App\Models\Event;
use App\Models\Registration;
use App\Models\RegistrationForm;
use App\Services\RegistrationSubmissionService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class RegistrationController extends Controller
{
    public function index(Request $request, Event $event): View
    {
        Gate::authorize('viewAny', [Registration::class, $event]);
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:100'], 'status' => ['nullable', 'in:pending,approved,rejected'],
            'source' => ['nullable', 'in:public,offline'], 'form_id' => ['nullable', 'integer'],
        ]);
        $registrations = $this->filtered($event, $filters)
            ->with(['form', 'guest'])->orderByDesc('submitted_at')->paginate(25)->withQueryString();

        return view('events.registrations.index', [
            'event' => $event, 'registrations' => $registrations,
            'forms' => $event->registrationForms()->active()->get(), 'filters' => $filters,
        ]);
    }

    public function create(Event $event, RegistrationForm $registrationForm): View
    {
        $this->guardForm($event, $registrationForm);
        Gate::authorize('submitOffline', $registrationForm);

        return view('events.registrations.entry', ['event' => $event, 'registrationForm' => $registrationForm->load('fields')]);
    }

    public function store(RegistrationSubmissionRequest $request, Event $event, RegistrationForm $registrationForm, RegistrationSubmissionService $service): RedirectResponse
    {
        $this->guardForm($event, $registrationForm);
        $registration = $service->submit($registrationForm, $request->validated(), 'offline', $request->user());

        return redirect()->route('events.registrations.show', [$event, $registration])->with('status', 'Offline registration recorded.');
    }

    public function show(Event $event, Registration $registration): View
    {
        $this->guardRegistration($event, $registration);
        Gate::authorize('view', $registration);
        $registration->load(['form', 'responses', 'statusHistory.actor', 'guest', 'outboundMessages']);

        return view('events.registrations.show', ['event' => $event, 'registration' => $registration]);
    }

    public function export(Request $request, Event $event): StreamedResponse
    {
        Gate::authorize('export', [Registration::class, $event]);
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:100'], 'status' => ['nullable', 'in:pending,approved,rejected'],
            'source' => ['nullable', 'in:public,offline'], 'form_id' => ['nullable', 'integer'],
        ]);
        $rows = $this->filtered($event, $filters)->with(['form', 'responses'])->orderBy('submitted_at')->get();
        $fieldLabels = $rows->flatMap->responses->pluck('field_label', 'field_key')->all();

        return response()->streamDownload(function () use ($rows, $fieldLabels) {
            $output = fopen('php://output', 'wb');
            fputcsv($output, array_merge(['Reference', 'Form', 'Name', 'Email', 'Phone', 'Source', 'Status', 'Submitted'], array_values($fieldLabels)));
            foreach ($rows as $registration) {
                $answers = $registration->responses->keyBy('field_key');
                fputcsv($output, array_merge([
                    $registration->reference_number, $registration->form->name, $registration->registrant_name,
                    $registration->registrant_email, $registration->registrant_phone, $registration->source,
                    $registration->status, $registration->submitted_at?->toIso8601String(),
                ], collect(array_keys($fieldLabels))->map(fn ($key) => ($response = $answers->get($key)) ? ($response->value_json ? implode('; ', $response->value_json) : $response->value_text) : null)->all()));
            }
            fclose($output);
        }, 'event-'.$event->getKey().'-registrations.csv', ['Content-Type' => 'text/csv']);
    }

    private function filtered(Event $event, array $filters)
    {
        return Registration::query()->where('event_id', $event->getKey())->search($filters['q'] ?? null)
            ->when($filters['status'] ?? null, fn ($query, $value) => $query->where('status', $value))
            ->when($filters['source'] ?? null, fn ($query, $value) => $query->where('source', $value))
            ->when($filters['form_id'] ?? null, fn ($query, $value) => $query->where('registration_form_id', $value));
    }

    private function guardForm(Event $event, RegistrationForm $form): void
    {
        abort_unless($form->event_id === $event->getKey(), 404);
    }

    private function guardRegistration(Event $event, Registration $registration): void
    {
        abort_unless($registration->event_id === $event->getKey(), 404);
    }
}
