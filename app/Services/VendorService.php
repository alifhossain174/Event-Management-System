<?php

namespace App\Services;

use App\Models\User;
use App\Models\Vendor;
use App\Models\VendorContact;
use App\Models\VendorServiceArea;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class VendorService
{
    public function __construct(private readonly AuditService $audit, private readonly StatusTransitionService $statuses, private readonly BranchScope $branches) {}

    public function create(array $data, User $actor): Vendor
    {
        return DB::transaction(function () use ($data, $actor) {
            $categories = $data['category_ids'] ?? [];
            unset($data['category_ids']);
            $this->ensureBranch($actor, $data['branch_id'] ?? null);
            $vendor = Vendor::query()->create($this->normalize($data) + ['status' => 'active', 'created_by_user_id' => $actor->id, 'updated_by_user_id' => $actor->id]);
            $vendor->categories()->sync($categories);
            $this->audit->record('vendor.created', $vendor, [], $this->snapshot($vendor), $actor);

            return $vendor;
        });
    }

    public function update(Vendor $vendor, array $data, User $actor): Vendor
    {
        return DB::transaction(function () use ($vendor, $data, $actor) {
            $locked = Vendor::query()->lockForUpdate()->findOrFail($vendor->id);
            $categories = $data['category_ids'] ?? [];
            unset($data['category_ids']);
            $this->ensureBranch($actor, $data['branch_id'] ?? null);
            $before = $this->snapshot($locked);
            $locked->update($this->normalize($data) + ['updated_by_user_id' => $actor->id]);
            $locked->categories()->sync($categories);
            $this->audit->record('vendor.updated', $locked, $before, $this->snapshot($locked), $actor);

            return $locked->fresh();
        });
    }

    public function changeStatus(Vendor $vendor, string $action, User $actor, ?string $reason): void
    {
        $target = $action === 'archive' ? 'archived' : 'active';
        DB::transaction(function () use ($vendor, $target, $actor, $reason) {
            $vendor->update(['archived_at' => $target === 'archived' ? now() : null, 'archived_by_user_id' => $target === 'archived' ? $actor->id : null,
                'archive_reason' => $target === 'archived' ? $reason : null, 'updated_by_user_id' => $actor->id]);
            $this->statuses->transition($vendor, $target, $actor, $reason, auditAction: "vendor.{$target}");
        });
    }

    public function linkUser(Vendor $vendor, ?User $user, User $actor): void
    {
        $this->validateUser($user, 'vendor');
        DB::transaction(function () use ($vendor, $user, $actor) {
            $locked = Vendor::query()->lockForUpdate()->findOrFail($vendor->id);
            if ($user && Vendor::query()->where('user_id', $user->id)->whereKeyNot($locked->id)->exists()) {
                throw ValidationException::withMessages(['user_id' => 'That user is already linked to another vendor.']);
            }
            $before = $locked->user_id;
            $locked->update(['user_id' => $user?->id, 'updated_by_user_id' => $actor->id]);
            $this->audit->record('vendor.user_link_changed', $locked, ['user_id' => $before], ['user_id' => $user?->id], $actor);
        });
    }

    public function addContact(Vendor $vendor, array $data, User $actor): VendorContact
    {
        return DB::transaction(function () use ($vendor, $data, $actor) {
            if ($data['is_primary'] ?? false) {
                $vendor->contacts()->update(['is_primary' => false]);
            }
            $contact = $vendor->contacts()->create($this->normalizeContact($data));
            $this->audit->record('vendor.contact.created', $contact, [], ['vendor_id' => $vendor->id, 'name' => $contact->name], $actor);

            return $contact;
        });
    }

    public function removeContact(VendorContact $contact, User $actor): void
    {
        DB::transaction(function () use ($contact, $actor) {
            $this->audit->record('vendor.contact.archived', $contact, ['vendor_id' => $contact->vendor_id, 'name' => $contact->name], [], $actor);
            $contact->delete();
        });
    }

    public function addServiceArea(Vendor $vendor, array $data, User $actor): VendorServiceArea
    {
        $data['normalized_name'] = $this->normalizeName($data['name']);
        if ($vendor->serviceAreas()->where('normalized_name', $data['normalized_name'])->exists()) {
            throw ValidationException::withMessages(['name' => 'That service area already exists for this vendor.']);
        }
        $area = $vendor->serviceAreas()->create($data + ['is_active' => true]);
        $this->audit->record('vendor.service_area.created', $area, [], ['vendor_id' => $vendor->id, 'name' => $area->name], $actor);

        return $area;
    }

    public function removeServiceArea(VendorServiceArea $area, User $actor): void
    {
        DB::transaction(function () use ($area, $actor) {
            $this->audit->record('vendor.service_area.archived', $area, ['vendor_id' => $area->vendor_id, 'name' => $area->name], [], $actor);
            $area->delete();
        });
    }

    private function normalize(array $data): array
    {
        $data['display_name'] = Str::squish($data['display_name']);
        $data['normalized_name'] = $this->normalizeName($data['display_name']);
        $data['normalized_email'] = filled($data['primary_email'] ?? null) ? mb_strtolower(trim($data['primary_email'])) : null;
        $data['normalized_phone'] = filled($data['primary_phone'] ?? null) ? preg_replace('/\D+/', '', $data['primary_phone']) : null;
        $data['country_code'] = filled($data['country_code'] ?? null) ? mb_strtoupper($data['country_code']) : null;

        return $data;
    }

    private function normalizeContact(array $data): array
    {
        $data['normalized_email'] = filled($data['email'] ?? null) ? mb_strtolower($data['email']) : null;
        $data['normalized_phone'] = filled($data['phone'] ?? null) ? preg_replace('/\D+/', '', $data['phone']) : null;

        return $data;
    }

    private function normalizeName(string $value): string
    {
        return Str::of($value)->lower()->replaceMatches('/[^\pL\pN]+/u', ' ')->squish()->toString();
    }

    private function snapshot(Vendor $vendor): array
    {
        return ['display_name' => $vendor->display_name, 'branch_id' => $vendor->branch_id, 'status' => $vendor->status, 'availability_conflict_policy' => $vendor->availability_conflict_policy, 'category_ids' => $vendor->categories()->pluck('vendor_categories.id')->all()];
    }

    private function ensureBranch(User $actor, mixed $branch): void
    {
        if (! $this->branches->permits($actor, $branch ? (int) $branch : null)) {
            throw ValidationException::withMessages(['branch_id' => 'You cannot assign that branch.']);
        }
    }

    private function validateUser(?User $user, string $role): void
    {
        if ($user && (! $user->is_active || $user->trashed() || ! $user->hasRole($role))) {
            throw ValidationException::withMessages(['user_id' => "Select an active {$role}-role user."]);
        }
    }
}
