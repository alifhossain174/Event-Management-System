<?php

namespace App\Services;

use App\Models\Event;
use App\Models\RegistrationField;
use App\Models\RegistrationForm;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class RegistrationFormService
{
    public function __construct(private readonly AuditService $audit, private readonly RegistrationValidationService $validation) {}

    public function create(Event $event, array $data, User $actor): RegistrationForm
    {
        return DB::transaction(function () use ($event, $data, $actor) {
            $form = RegistrationForm::query()->create($this->attributes($event, $data) + [
                'public_slug' => Str::random(48),
                'created_by_user_id' => $actor->getKey(),
                'updated_by_user_id' => $actor->getKey(),
            ]);
            $this->audit->record('registration_form.created', $form, [], ['event_id' => $event->getKey(), 'name' => $form->name], $actor);

            return $form;
        });
    }

    public function update(RegistrationForm $form, array $data, User $actor): RegistrationForm
    {
        return DB::transaction(function () use ($form, $data, $actor) {
            $form = RegistrationForm::query()->lockForUpdate()->findOrFail($form->getKey());
            $before = $form->only(['name', 'privacy_text', 'duplicate_policy', 'approval_required', 'confirmation_channel', 'is_active']);
            $form->update($this->attributes($form->event, $data) + ['updated_by_user_id' => $actor->getKey()]);
            $this->audit->record('registration_form.updated', $form, $before, $form->only(array_keys($before)), $actor);

            return $form->refresh();
        });
    }

    public function publish(RegistrationForm $form, bool $publish, User $actor): RegistrationForm
    {
        if ($publish && ! $form->fields()->where('is_active', true)->whereNull('archived_at')->exists()) {
            throw ValidationException::withMessages(['publish' => 'Add at least one active field before publishing.']);
        }
        $form->update(['published_at' => $publish ? now() : null, 'updated_by_user_id' => $actor->getKey()]);
        $this->audit->record($publish ? 'registration_form.published' : 'registration_form.unpublished', $form, [], ['published_at' => $form->published_at], $actor);

        return $form->refresh();
    }

    public function saveField(RegistrationForm $form, array $data, User $actor, ?RegistrationField $field = null): RegistrationField
    {
        abort_if($field && $field->registration_form_id !== $form->getKey(), 404);
        $options = in_array($data['type'], ['select', 'radio', 'checkbox'], true) ? array_values(array_unique($data['options'] ?? [])) : null;
        $attributes = [
            'event_id' => $form->event_id,
            'registration_form_id' => $form->getKey(),
            'key' => Str::snake($data['key']),
            'type' => $data['type'],
            'label' => trim($data['label']),
            'help_text' => $data['help_text'] ?? null,
            'options' => $options,
            'validation_constraints' => $this->validation->normalizedConstraints($data['type'], $data),
            'is_required' => (bool) ($data['is_required'] ?? false),
            'is_active' => (bool) ($data['is_active'] ?? true),
            'display_order' => (int) ($data['display_order'] ?? (($form->fields()->max('display_order') ?? 0) + 10)),
            'updated_by_user_id' => $actor->getKey(),
        ];
        $field ? $field->update($attributes) : $field = RegistrationField::query()->create($attributes + ['created_by_user_id' => $actor->getKey()]);
        $this->audit->record('registration_field.saved', $field, [], ['form_id' => $form->getKey(), 'key' => $field->key, 'type' => $field->type], $actor);

        return $field->refresh();
    }

    /** @param list<int> $fieldIds */
    public function reorder(RegistrationForm $form, array $fieldIds, User $actor): void
    {
        DB::transaction(function () use ($form, $fieldIds, $actor) {
            $existing = $form->fields()->whereIn('id', $fieldIds)->pluck('id')->map(fn ($id) => (int) $id)->all();
            if (count($existing) !== count(array_unique($fieldIds))) {
                throw ValidationException::withMessages(['field_ids' => 'Every selected field must belong to this form.']);
            }
            foreach ($fieldIds as $index => $id) {
                RegistrationField::query()->whereKey($id)->update(['display_order' => ($index + 1) * 10, 'updated_by_user_id' => $actor->getKey()]);
            }
            $this->audit->record('registration_fields.reordered', $form, [], ['field_ids' => $fieldIds], $actor);
        });
    }

    private function attributes(Event $event, array $data): array
    {
        return [
            'event_id' => $event->getKey(), 'name' => trim($data['name']),
            'privacy_text' => $data['privacy_text'] ?? null,
            'duplicate_policy' => $data['duplicate_policy'],
            'approval_required' => (bool) ($data['approval_required'] ?? true),
            'confirmation_channel' => $data['confirmation_channel'] ?? 'none',
            'is_active' => (bool) ($data['is_active'] ?? true),
        ];
    }
}
