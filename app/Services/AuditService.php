<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

final class AuditService
{
    public function record(
        string $action,
        ?Model $subject = null,
        array $before = [],
        array $after = [],
        ?User $actor = null,
        ?Request $request = null,
    ): AuditLog {
        $request ??= request();

        return AuditLog::query()->create([
            'actor_user_id' => $actor?->getKey() ?? auth()->id(),
            'action' => $action,
            'subject_type' => $subject?->getMorphClass(),
            'subject_id' => $subject?->getKey(),
            'before_values' => ($sanitizedBefore = $this->sanitize($before)) ?: null,
            'after_values' => ($sanitizedAfter = $this->sanitize($after)) ?: null,
            'ip_address' => $request?->ip(),
            'user_agent' => $request?->userAgent() ? Str::limit($request->userAgent(), 1000, '') : null,
            'occurred_at' => now(),
        ]);
    }

    /** @param array<string|int, mixed> $metadata */
    public function sanitize(array $metadata): array
    {
        $sanitized = [];

        foreach ($metadata as $key => $value) {
            $normalizedKey = mb_strtolower((string) $key);

            if (preg_match('/password|passphrase|token|secret|api[_-]?key|authorization|cookie|csrf|card[_-]?number|cvv|bank[_-]?account|dietary/', $normalizedKey)) {
                $sanitized[$key] = '[REDACTED]';

                continue;
            }

            if (is_array($value)) {
                $sanitized[$key] = $this->sanitize($value);
            } elseif (is_string($value)) {
                $sanitized[$key] = Str::limit($value, 2000, '…');
            } elseif (is_scalar($value) || $value === null) {
                $sanitized[$key] = $value;
            } else {
                $sanitized[$key] = '['.class_basename($value).']';
            }
        }

        return $sanitized;
    }
}
