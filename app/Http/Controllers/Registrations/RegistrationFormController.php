<?php

namespace App\Http\Controllers\Registrations;

use App\Http\Controllers\Controller;
use App\Http\Requests\Registrations\RegistrationFormRequest;
use App\Models\Event;
use App\Models\RegistrationForm;
use App\Services\RegistrationFormService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

final class RegistrationFormController extends Controller
{
    public function index(Event $event): View
    {
        Gate::authorize('viewAny', [RegistrationForm::class, $event]);

        return view('events.registrations.forms.index', [
            'event' => $event,
            'forms' => $event->registrationForms()->withCount('registrations')->paginate(20),
        ]);
    }

    public function create(Event $event): View
    {
        Gate::authorize('create', [RegistrationForm::class, $event]);

        return view('events.registrations.forms.form', ['event' => $event, 'registrationForm' => new RegistrationForm]);
    }

    public function store(RegistrationFormRequest $request, Event $event, RegistrationFormService $service): RedirectResponse
    {
        $form = $service->create($event, $request->validated(), $request->user());

        return redirect()->route('events.registration-forms.edit', [$event, $form])->with('status', 'Registration form created. Add fields before publishing.');
    }

    public function edit(Event $event, RegistrationForm $registrationForm): View
    {
        $this->guardEvent($event, $registrationForm);
        Gate::authorize('configure', $registrationForm);

        return view('events.registrations.forms.form', [
            'event' => $event,
            'registrationForm' => $registrationForm->load('fields'),
        ]);
    }

    public function update(RegistrationFormRequest $request, Event $event, RegistrationForm $registrationForm, RegistrationFormService $service): RedirectResponse
    {
        $this->guardEvent($event, $registrationForm);
        $service->update($registrationForm, $request->validated(), $request->user());

        return back()->with('status', 'Registration form updated. Existing response snapshots were unchanged.');
    }

    public function publish(Request $request, Event $event, RegistrationForm $registrationForm, RegistrationFormService $service): RedirectResponse
    {
        $this->guardEvent($event, $registrationForm);
        Gate::authorize('configure', $registrationForm);
        $data = $request->validate(['publish' => ['required', 'boolean']]);
        $service->publish($registrationForm, (bool) $data['publish'], $request->user());

        return back()->with('status', $data['publish'] ? 'Registration form published.' : 'Registration form unpublished.');
    }

    private function guardEvent(Event $event, RegistrationForm $form): void
    {
        abort_unless($form->event_id === $event->getKey(), 404);
    }
}
