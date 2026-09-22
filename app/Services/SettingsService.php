<?php

namespace App\Services;

use App\Models\SystemSetting;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use LogicException;

final class SettingsService
{
    public function __construct(private readonly AuditService $audit) {}

    public function get(string $key): mixed
    {
        $definition = $this->definition($key);

        if ($definition['type'] === 'secret') {
            throw new LogicException('Secret settings must be accessed through secret().');
        }

        $value = $this->storedValue($key);

        return $value === null
            ? $definition['default']
            : $this->cast($value, $definition['type']);
    }

    public function string(string $key): string
    {
        return (string) $this->get($key);
    }

    public function boolean(string $key): bool
    {
        return (bool) $this->get($key);
    }

    public function integer(string $key): int
    {
        return (int) $this->get($key);
    }

    public function decimal(string $key): string
    {
        return (string) $this->get($key);
    }

    public function secret(string $key): ?string
    {
        $definition = $this->definition($key);

        if ($definition['type'] !== 'secret') {
            throw new LogicException('Only secret settings may be accessed through secret().');
        }

        $setting = $this->setting($key);

        if (! $setting?->value) {
            return null;
        }

        return Crypt::decryptString($setting->value);
    }

    public function hasSecret(string $key): bool
    {
        return $this->setting($key)?->value !== null;
    }

    public function update(array $values, User $actor): void
    {
        $before = [];
        $after = [];

        DB::transaction(function () use ($values, $actor, &$before, &$after) {
            foreach ($values as $key => $value) {
                $definition = $this->definition($key);
                $isSecret = $definition['type'] === 'secret';
                $existing = SystemSetting::query()->where('key', $key)->first();

                if ($isSecret && ($value === null || $value === '')) {
                    continue;
                }

                $before[$key] = $isSecret
                    ? $this->maskedState((bool) $existing?->value)
                    : ($existing ? $this->cast($existing->value, $existing->type) : $definition['default']);

                $stored = $isSecret
                    ? Crypt::encryptString((string) $value)
                    : $this->serialize($value, $definition['type']);

                SystemSetting::query()->updateOrCreate(
                    ['key' => $key],
                    [
                        'group' => str($key)->before('.')->toString(),
                        'type' => $definition['type'],
                        'value' => $stored,
                        'is_encrypted' => $isSecret,
                        'updated_by_user_id' => $actor->id,
                    ],
                );

                Cache::forget($this->cacheKey($key));
                $after[$key] = $isSecret
                    ? $this->maskedState(true)
                    : $this->cast($stored, $definition['type']);
            }

            if ($after !== []) {
                $this->audit->record('settings.updated', null, $before, $after, $actor);
            }
        });
    }

    private function definition(string $key): array
    {
        $definition = config('system-settings.definitions')[$key] ?? null;

        if (! is_array($definition)) {
            throw new LogicException("Unknown setting key [{$key}].");
        }

        return $definition;
    }

    private function setting(string $key): ?SystemSetting
    {
        if (! Schema::hasTable('system_settings')) {
            return null;
        }

        return Cache::rememberForever(
            $this->cacheKey($key),
            fn () => SystemSetting::query()->where('key', $key)->first(),
        );
    }

    private function storedValue(string $key): ?string
    {
        return $this->setting($key)?->value;
    }

    private function serialize(mixed $value, string $type): ?string
    {
        if ($value === null) {
            return null;
        }

        return match ($type) {
            'boolean' => $value ? '1' : '0',
            'integer' => (string) (int) $value,
            'decimal' => (string) $value,
            default => (string) $value,
        };
    }

    private function cast(?string $value, string $type): mixed
    {
        if ($value === null) {
            return null;
        }

        return match ($type) {
            'boolean' => filter_var($value, FILTER_VALIDATE_BOOLEAN),
            'integer' => (int) $value,
            'decimal', 'string' => $value,
            default => $value,
        };
    }

    private function maskedState(bool $configured): string
    {
        return $configured ? '[configured]' : '[not configured]';
    }

    private function cacheKey(string $key): string
    {
        return "system-setting:{$key}";
    }
}
