<?php

namespace App\Services;

use App\Models\Branch;
use App\Models\Company;
use App\Models\User;
use Illuminate\Support\Facades\DB;

final class BranchService
{
    public function __construct(private readonly AuditService $audit) {}

    public function create(array $data, User $actor): Branch
    {
        return DB::transaction(function () use ($data, $actor) {
            $data['company_id'] = Company::query()->value('id')
                ?? Company::query()->create(['name' => config('app.name')])->id;
            $branch = Branch::query()->create($data);
            $this->audit->record('branch.created', $branch, [], $this->snapshot($branch), $actor);

            return $branch;
        });
    }

    public function update(Branch $branch, array $data, User $actor): Branch
    {
        return DB::transaction(function () use ($branch, $data, $actor) {
            $before = $this->snapshot($branch);
            $branch->update($data);
            $this->audit->record('branch.updated', $branch, $before, $this->snapshot($branch), $actor);

            return $branch->fresh();
        });
    }

    public function archive(Branch $branch, User $actor): void
    {
        DB::transaction(function () use ($branch, $actor) {
            $before = $this->snapshot($branch);
            $branch->update(['is_active' => false]);
            $this->audit->record('branch.archived', $branch, $before, ['status' => 'archived'], $actor);
            $branch->delete();
        });
    }

    private function snapshot(Branch $branch): array
    {
        return $branch->only(['company_id', 'code', 'name', 'email', 'phone', 'city', 'timezone', 'is_active']);
    }
}
