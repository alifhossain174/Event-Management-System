<?php

namespace App\Services;

use App\Models\Event;
use App\Models\EventNote;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class EventNoteService
{
    public function __construct(
        private readonly AuditService $audit,
        private readonly EventTimelineService $timeline,
    ) {}

    public function create(Event $event, string $body, bool $isPinned, bool $includeInDuplicate, User $actor): EventNote
    {
        if ($event->isOperationallyReadOnly()) {
            throw ValidationException::withMessages(['body' => 'Completed, cancelled, or archived Events cannot receive operational notes.']);
        }

        return DB::transaction(function () use ($event, $body, $isPinned, $includeInDuplicate, $actor) {
            $note = $event->notes()->create([
                'body' => $body,
                'is_pinned' => $isPinned,
                'include_in_duplicate' => $includeInDuplicate,
                'created_by_user_id' => $actor->getKey(),
            ]);
            $this->timeline->record($event, 'note', 'Event note added', $actor, null, ['note_id' => $note->getKey()]);
            $this->audit->record('event.note_created', $event, [], [
                'note_id' => $note->getKey(),
                'is_pinned' => $isPinned,
                'include_in_duplicate' => $includeInDuplicate,
            ], $actor);

            return $note;
        });
    }
}
