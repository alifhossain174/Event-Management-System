<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class MasterDataService
{
    public function __construct(private readonly AuditService $audit) {}

    public function create(string $type, string $modelClass, array $data, User $actor): Model
    {
        return DB::transaction(function () use ($type, $modelClass, $data, $actor) {
            $data['slug'] = Str::slug($data['slug'] ?: $data['name']);
            $category = $modelClass::query()->create($data);
            $this->audit->record("master-data.{$type}.created", $category, [], $this->snapshot($category), $actor);

            return $category;
        });
    }

    public function update(string $type, Model $category, array $data, User $actor): Model
    {
        return DB::transaction(function () use ($type, $category, $data, $actor) {
            $before = $this->snapshot($category);
            $data['slug'] = Str::slug($data['slug'] ?: $data['name']);
            $category->update($data);
            $this->audit->record("master-data.{$type}.updated", $category, $before, $this->snapshot($category), $actor);

            return $category->fresh();
        });
    }

    public function archive(string $type, Model $category, User $actor): void
    {
        DB::transaction(function () use ($type, $category, $actor) {
            $before = $this->snapshot($category);
            $category->update(['is_active' => false]);
            $this->audit->record("master-data.{$type}.archived", $category, $before, ['status' => 'archived'], $actor);
            $category->delete();
        });
    }

    private function snapshot(Model $category): array
    {
        return $category->only(['name', 'slug', 'description', 'direction', 'is_active', 'sort_order']);
    }
}
