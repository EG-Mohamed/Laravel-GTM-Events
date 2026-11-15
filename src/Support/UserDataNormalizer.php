<?php

namespace MohamedSaid\LaravelGa4Events\Support;

use Illuminate\Support\Str;

final class UserDataNormalizer
{
    public static function hash(?string $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (preg_match('/^[a-f0-9]{64}$/', $value) === 1) {
            return $value;
        }

        return hash('sha256', $value);
    }

    public static function email(mixed $value): ?string
    {
        $value = is_scalar($value) ? Str::lower(trim((string) $value)) : '';

        return filter_var($value, FILTER_VALIDATE_EMAIL) ? $value : null;
    }

    public static function phoneDigits(mixed $value): ?string
    {
        $digits = preg_replace('/\D+/', '', is_scalar($value) ? (string) $value : '');
        $digits = preg_replace('/^00/', '', (string) $digits);

        return strlen((string) $digits) >= 7 ? $digits : null;
    }

    public static function name(mixed $value): ?string
    {
        $value = is_scalar($value) ? Str::lower(trim((string) $value)) : '';
        $value = preg_replace('/[\p{P}\p{S}\s]+/u', '', $value);

        return $value !== '' ? $value : null;
    }

    public static function simple(mixed $value): ?string
    {
        $value = is_scalar($value) ? Str::lower(preg_replace('/\s+/', '', trim((string) $value))) : '';

        return $value !== '' ? $value : null;
    }

    public static function normalize(array $data): array
    {
        $firstName = $data['first_name'] ?? null;
        $lastName = $data['last_name'] ?? null;

        if (($firstName === null || $firstName === '') && is_string($data['name'] ?? null)) {
            $parts = preg_split('/\s+/', trim($data['name']), 2);
            $firstName = $parts[0] ?? null;
            $lastName = $lastName ?: ($parts[1] ?? null);
        }

        return array_filter([
            'email' => self::email($data['email'] ?? null),
            'phone' => self::phoneDigits($data['phone'] ?? null),
            'first_name' => self::name($firstName),
            'last_name' => self::name($lastName),
            'city' => self::name($data['city'] ?? null),
            'state' => self::simple($data['state'] ?? null),
            'zip' => self::simple($data['zip'] ?? null),
            'country' => self::simple($data['country'] ?? null),
            'external_id' => is_scalar($data['external_id'] ?? null) && (string) $data['external_id'] !== '' ? (string) $data['external_id'] : null,
        ], fn (?string $value): bool => $value !== null);
    }

    public static function forMeta(array $normalized): array
    {
        return array_filter([
            'em' => isset($normalized['email']) ? [self::hash($normalized['email'])] : null,
            'ph' => isset($normalized['phone']) ? [self::hash($normalized['phone'])] : null,
            'fn' => isset($normalized['first_name']) ? [self::hash($normalized['first_name'])] : null,
            'ln' => isset($normalized['last_name']) ? [self::hash($normalized['last_name'])] : null,
            'ct' => isset($normalized['city']) ? [self::hash($normalized['city'])] : null,
            'st' => isset($normalized['state']) ? [self::hash($normalized['state'])] : null,
            'zp' => isset($normalized['zip']) ? [self::hash($normalized['zip'])] : null,
            'country' => isset($normalized['country']) ? [self::hash($normalized['country'])] : null,
            'external_id' => isset($normalized['external_id']) ? [self::hash($normalized['external_id'])] : null,
        ]);
    }

    public static function forMetaPixel(array $normalized): array
    {
        return array_map(fn (array $values): string => $values[0], self::forMeta($normalized));
    }

    public static function forGa4(array $normalized): array
    {
        $address = array_filter([
            'sha256_first_name' => self::hash($normalized['first_name'] ?? null),
            'sha256_last_name' => self::hash($normalized['last_name'] ?? null),
            'city' => $normalized['city'] ?? null,
            'region' => $normalized['state'] ?? null,
            'postal_code' => $normalized['zip'] ?? null,
            'country' => isset($normalized['country']) ? Str::upper($normalized['country']) : null,
        ]);

        return array_filter([
            'sha256_email_address' => isset($normalized['email']) ? [self::hash($normalized['email'])] : null,
            'sha256_phone_number' => isset($normalized['phone']) ? [self::hash('+'.$normalized['phone'])] : null,
            'address' => $address !== [] ? [$address] : null,
        ]);
    }
}
