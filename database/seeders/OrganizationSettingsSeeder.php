<?php

namespace Database\Seeders;

use App\Models\Company;
use App\Models\SystemSetting;
use Illuminate\Database\Seeder;

final class OrganizationSettingsSeeder extends Seeder
{
    public function run(): void
    {
        Company::query()->firstOrCreate(['id' => 1], ['name' => config('app.name')]);

        foreach (config('system-settings.definitions') as $key => $definition) {
            if ($definition['type'] === 'secret') {
                continue;
            }

            SystemSetting::query()->firstOrCreate(
                ['key' => $key],
                [
                    'group' => str($key)->before('.')->toString(),
                    'type' => $definition['type'],
                    'value' => $this->serialize($definition['default'], $definition['type']),
                    'is_encrypted' => false,
                ],
            );
        }
    }

    private function serialize(mixed $value, string $type): ?string
    {
        if ($value === null) {
            return null;
        }

        return match ($type) {
            'boolean' => $value ? '1' : '0',
            default => (string) $value,
        };
    }
}
