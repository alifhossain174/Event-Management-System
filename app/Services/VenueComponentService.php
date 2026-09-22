<?php

namespace App\Services;

use App\Models\SeatingPlan;
use App\Models\User;
use App\Models\Venue;
use App\Models\VenueFacility;
use App\Models\VenueMedia;
use App\Models\VenueRate;
use App\Models\VenueSpace;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

final class VenueComponentService
{
    public function __construct(private readonly AuditService $audit, private readonly DocumentService $documents) {}

    public function addSpace(Venue $venue, array $data, User $actor): VenueSpace
    {
        return $this->create($venue, VenueSpace::class, $data + ['is_active' => true], 'venue.space.created', $actor);
    }

    public function addFacility(Venue $venue, array $data, User $actor): VenueFacility
    {
        return $this->create($venue, VenueFacility::class, $data + ['is_active' => true], 'venue.facility.created', $actor);
    }

    public function addRate(Venue $venue, array $data, User $actor): VenueRate
    {
        return $this->create($venue, VenueRate::class, $data + ['is_active' => true], 'venue.rate.created', $actor);
    }

    public function addSeatingPlan(Venue $venue, array $data, User $actor): SeatingPlan
    {
        return $this->create($venue, SeatingPlan::class, $data + ['is_active' => true], 'venue.seating_plan.created', $actor);
    }

    public function addMedia(Venue $venue, UploadedFile $file, array $data, User $actor): VenueMedia
    {
        return DB::transaction(function () use ($venue, $file, $data, $actor) {
            if ($data['is_primary'] ?? false) {
                $venue->media()->update(['is_primary' => false]);
            }
            $document = $this->documents->create([
                'title' => $data['caption'] ?: $venue->name.' image',
                'description' => $data['alt_text'], 'branch_id' => $venue->branch_id,
                'version_notes' => 'Initial protected venue image',
            ], $file, [$venue], $actor);
            $media = $venue->media()->create([
                'document_id' => $document->getKey(), 'caption' => $data['caption'] ?? null,
                'alt_text' => $data['alt_text'], 'is_primary' => (bool) ($data['is_primary'] ?? false),
                'sort_order' => ((int) $venue->media()->max('sort_order')) + 1,
            ]);
            $this->audit->record('venue.media.created', $media, [], ['venue_id' => $venue->getKey(), 'document_id' => $document->getKey()], $actor);

            return $media;
        });
    }

    public function archive(Venue $venue, Model $component, User $actor): void
    {
        abort_unless((int) $component->getAttribute('venue_id') === (int) $venue->getKey(), 404);
        DB::transaction(function () use ($venue, $component, $actor) {
            $this->audit->record('venue.component.archived', $component, ['venue_id' => $venue->getKey(), 'type' => $component->getMorphClass()], [], $actor);
            $component->delete();
        });
    }

    /** @template T of Model
     * @param  class-string<T>  $model
     * @return T
     */
    private function create(Venue $venue, string $model, array $data, string $action, User $actor): Model
    {
        return DB::transaction(function () use ($venue, $model, $data, $action, $actor) {
            /** @var Model $component */
            $component = $model::query()->create($data + ['venue_id' => $venue->getKey()]);
            $this->audit->record($action, $component, [], ['venue_id' => $venue->getKey(), 'name' => $component->getAttribute('name') ?? $component->getAttribute('label')], $actor);

            return $component;
        });
    }
}
