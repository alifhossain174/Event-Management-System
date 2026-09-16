<?php

namespace App\Services;

use Illuminate\Support\Str;

final class ClientDataNormalizer
{
    /** @param array<string, mixed> $data */
    public function normalize(array $data): array
    {
        $type = $data['type'];
        $displayName = $type === 'organization'
            ? (string) $data['organization_name']
            : trim(collect([$data['first_name'] ?? null, $data['last_name'] ?? null])->filter()->join(' '));

        if ($type === 'individual') {
            $data = array_merge($data, [
                'organization_name' => null,
                'legal_name' => null,
                'registration_number' => null,
                'tax_identifier' => null,
            ]);
        } else {
            $data['first_name'] = null;
            $data['last_name'] = null;
        }

        $data['display_name'] = Str::squish($displayName);
        $data['normalized_name'] = $this->name($displayName);
        $data['normalized_email'] = $this->email($data['primary_email'] ?? null);
        $data['normalized_phone'] = $this->phone($data['primary_phone'] ?? null);
        $data['country_code'] = isset($data['country_code']) ? mb_strtoupper($data['country_code']) : null;

        return $data;
    }

    public function name(?string $value): ?string
    {
        if (! $value) {
            return null;
        }

        return Str::of($value)->lower()->replaceMatches('/[^\pL\pN]+/u', ' ')->squish()->toString();
    }

    public function email(?string $value): ?string
    {
        return $value ? mb_strtolower(trim($value)) : null;
    }

    public function phone(?string $value): ?string
    {
        if (! $value) {
            return null;
        }

        return preg_replace('/\D+/', '', $value) ?: null;
    }
}
