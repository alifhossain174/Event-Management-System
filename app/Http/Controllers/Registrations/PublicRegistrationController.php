<?php

namespace App\Http\Controllers\Registrations;

use App\Http\Controllers\Controller;
use App\Http\Requests\Registrations\RegistrationSubmissionRequest;
use App\Models\RegistrationForm;
use App\Services\RegistrationSubmissionService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Str;

final class PublicRegistrationController extends Controller
{
    public function show(RegistrationForm $registrationForm): View
    {
        $form = $this->publishedForm($registrationForm);

        return view('public.registrations.show', ['registrationForm' => $form, 'idempotencyKey' => (string) Str::uuid()]);
    }

    public function store(RegistrationSubmissionRequest $request, RegistrationForm $registrationForm, RegistrationSubmissionService $service): RedirectResponse
    {
        $form = $this->publishedForm($registrationForm);
        $registration = $service->submit($form, $request->validated(), 'public');

        return redirect()->route('public.registrations.thanks', [$form->public_slug, $registration->reference_number]);
    }

    public function thanks(RegistrationForm $registrationForm, string $reference): View
    {
        $form = $this->publishedForm($registrationForm);
        $registration = $form->registrations()->where('reference_number', $reference)->firstOrFail();

        return view('public.registrations.thanks', compact('form', 'registration'));
    }

    private function publishedForm(RegistrationForm $form): RegistrationForm
    {
        $form->load(['event.moduleSettings', 'fields']);
        abort_unless($form->isPublished(), 404);
        abort_unless($form->event->moduleSettings->contains(fn ($setting) => $setting->module_key === 'registration' && $setting->is_enabled), 404);
        abort_if($form->event->archived_at || in_array($form->event->status, ['completed', 'cancelled'], true), 404);

        return $form;
    }
}
