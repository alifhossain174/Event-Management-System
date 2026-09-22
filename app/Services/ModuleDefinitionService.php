<?php

namespace App\Services;

use App\Models\ModuleDefinition;
use App\Models\User;
use Illuminate\Support\Facades\DB;

final class ModuleDefinitionService
{
    public function __construct(private readonly AuditService $audit) {}

    /** @param array<string, mixed> $data */
    public function update(ModuleDefinition $definition, array $data, User $actor): ModuleDefinition
    {
        return DB::transaction(function () use ($definition, $data, $actor) {
            $locked = ModuleDefinition::query()->lockForUpdate()->findOrFail($definition->getKey());
            $before = $locked->only(['label', 'sort_order', 'is_active']);
            $locked->update($data);
            $this->audit->record('module-definition.updated', $locked, $before, $locked->only(['label', 'sort_order', 'is_active']), $actor);

            return $locked->fresh();
        });
    }
}
