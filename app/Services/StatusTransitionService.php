<?php

namespace App\Services;

use App\Contracts\TracksStatusHistory;
use App\Models\StatusHistory;
use App\Models\User;
use DomainException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

final class StatusTransitionService
{
    public function __construct(private readonly AuditService $audit) {}

    /**
     * @param  array<string, mixed>  $metadata
     */
    public function transition(
        Model&TracksStatusHistory $subject,
        string $toStatus,
        ?User $actor,
        ?string $reason = null,
        array $metadata = [],
        string $auditAction = 'status.changed',
    ): StatusHistory {
        $history = DB::transaction(function () use ($subject, $toStatus, $actor, $reason, $metadata, $auditAction) {
            /** @var Model&TracksStatusHistory $locked */
            $locked = $subject->newQuery()->lockForUpdate()->findOrFail($subject->getKey());
            $column = $locked->statusHistoryColumn();
            $fromStatus = (string) $locked->getAttribute($column);
            $allowed = $locked->allowedStatusTransitions()[$fromStatus] ?? [];

            if (! in_array($toStatus, $allowed, true)) {
                throw new DomainException("Status transition from {$fromStatus} to {$toStatus} is not allowed.");
            }

            $locked->setAttribute($column, $toStatus);
            $locked->save();

            $history = StatusHistory::query()->create([
                'subject_type' => $locked->getMorphClass(),
                'subject_id' => $locked->getKey(),
                'from_status' => $fromStatus,
                'to_status' => $toStatus,
                'actor_type' => $actor ? 'user' : 'system',
                'actor_user_id' => $actor?->getKey(),
                'reason' => $reason,
                'metadata' => $this->audit->sanitize($metadata) ?: null,
                'changed_at' => now(),
            ]);

            $this->audit->record(
                $auditAction,
                $locked,
                [$column => $fromStatus],
                [$column => $toStatus, 'reason' => $reason],
                $actor,
            );

            return $history;
        });

        $subject->refresh();

        return $history;
    }
}
