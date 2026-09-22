<?php

namespace App\Http\Controllers\Registrations;

use App\Http\Controllers\Controller;
use App\Http\Requests\Registrations\RegistrationFieldRequest;
use App\Http\Requests\Registrations\ReorderRegistrationFieldsRequest;
use App\Models\Event;
use App\Models\RegistrationField;
use App\Models\RegistrationForm;
use App\Services\RegistrationFormService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;

final class RegistrationFieldController extends Controller
{
    public function edit(Event $event, RegistrationForm $registrationForm, RegistrationField $registrationField): View
    {
        $this->guard($event, $registrationForm, $registrationField);
        Gate::authorize('configure', $registrationForm);

        return view('events.registrations.forms.field', compact('event', 'registrationForm', 'registrationField'));
    }

    public function store(RegistrationFieldRequest $request, Event $event, RegistrationForm $registrationForm, RegistrationFormService $service): RedirectResponse
    {
        $this->guard($event, $registrationForm);
        $service->saveField($registrationForm, $request->validated(), $request->user());

        return back()->with('status', 'Registration field added.');
    }

    public function update(RegistrationFieldRequest $request, Event $event, RegistrationForm $registrationForm, RegistrationField $registrationField, RegistrationFormService $service): RedirectResponse
    {
        $this->guard($event, $registrationForm, $registrationField);
        $service->saveField($registrationForm, $request->validated(), $request->user(), $registrationField);

        return back()->with('status', 'Registration field updated. Existing response snapshots were unchanged.');
    }

    public function reorder(ReorderRegistrationFieldsRequest $request, Event $event, RegistrationForm $registrationForm, RegistrationFormService $service): RedirectResponse
    {
        $this->guard($event, $registrationForm);
        $positions = $request->validated('positions');
        $ids = collect($request->validated('field_ids'))->sortBy(fn ($id) => (int) ($positions[$id] ?? 65000))->values()->all();
        $service->reorder($registrationForm, $ids, $request->user());

        return back()->with('status', 'Field order updated.');
    }

    private function guard(Event $event, RegistrationForm $form, ?RegistrationField $field = null): void
    {
        abort_unless($form->event_id === $event->getKey(), 404);
        abort_if($field && $field->registration_form_id !== $form->getKey(), 404);
    }
}
