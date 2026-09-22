<?php

namespace App\Services;

use App\Models\User;
use App\Models\Vendor;
use App\Models\VendorAssignment;
use App\Models\VendorAvailability;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

final class VendorAvailabilityService
{
    public function __construct(private readonly AuditService $audit) {}

    /** @param array<string, mixed> $data */
    public function add(Vendor $vendor, array $data, User $actor): VendorAvailability
    {
        return DB::transaction(function () use ($vendor, $data, $actor) {
            $availability = $vendor->availabilities()->create($data + ['created_by_user_id' => $actor->getKey()]);
            $this->audit->record('vendor.availability.created', $availability, [], [
                'vendor_id' => $vendor->getKey(), 'starts_at' => $availability->starts_at->toIso8601String(),
                'ends_at' => $availability->ends_at->toIso8601String(), 'status' => $availability->status,
                'conflict_action' => $availability->conflict_action,
            ], $actor);

            return $availability;
        });
    }

    public function archive(Vendor $vendor, VendorAvailability $availability, User $actor): void
    {
        abort_unless($availability->vendor_id === $vendor->getKey(), 404);
        DB::transaction(function () use ($availability, $actor) {
            $this->audit->record('vendor.availability.archived', $availability, [
                'vendor_id' => $availability->vendor_id, 'status' => $availability->status,
            ], [], $actor);
            $availability->delete();
        });
    }

    /** @return array{blocked: bool, warnings: list<string>} */
    public function evaluate(Vendor $vendor, CarbonImmutable $startsAt, CarbonImmutable $endsAt, ?int $exceptAssignmentId = null, bool $lock = false): array
    {
        $warnings = [];
        $blocked = false;
        $rules = VendorAvailability::query()->where('vendor_id', $vendor->getKey())
            ->where('status', 'unavailable')->where('starts_at', '<', $endsAt)->where('ends_at', '>', $startsAt);
        if ($lock) {
            $rules->lockForUpdate();
        }

        foreach ($rules->get() as $rule) {
            $message = 'Vendor availability marks this time unavailable'.($rule->notes ? ': '.$rule->notes : '.');
            $warnings[] = $message;
            $blocked = $blocked || $rule->conflict_action === 'block';
        }

        $assignments = VendorAssignment::query()->where('vendor_id', $vendor->getKey())
            ->whereIn('status', VendorAssignment::ACTIVE_STATUSES)
            ->where('scheduled_starts_at', '<', $endsAt)->where('scheduled_ends_at', '>', $startsAt)
            ->when($exceptAssignmentId, fn ($query, $id) => $query->whereKeyNot($id))->with('event:id,reference_number,name');
        if ($lock) {
            $assignments->lockForUpdate();
        }

        foreach ($assignments->get() as $assignment) {
            $warnings[] = "Overlaps {$assignment->event->reference_number} ({$assignment->event->name}).";
            $blocked = $blocked || $vendor->availability_conflict_policy === 'block';
        }

        return ['blocked' => $blocked, 'warnings' => array_values(array_unique($warnings))];
    }
}
