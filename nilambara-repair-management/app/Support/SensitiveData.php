<?php

namespace App\Support;

use InvalidArgumentException;
use JsonException;

/**
 * Protects credential-shaped JSON fields, not arbitrary secret-looking strings.
 * Raw SQL and direct raw-attribute access bypass model protections.
 */
final class SensitiveData
{
    public const REDACTED = '[REDACTED]';

    /** @var list<string> */
    private const CREDENTIAL_NAMES = [
        'password', 'passwordconfirmation', 'remembertoken', 'token', 'secret',
        'apikey', 'appkey', 'dbpassword', 'authorization', 'cookie', 'privatekey',
        'credential', 'credentials',
    ];

    public static function isSensitiveKey(string|int $key): bool
    {
        if (! is_string($key)) {
            return false;
        }

        $normalized = strtolower((string) preg_replace('/[^a-z0-9]/i', '', $key));

        foreach (self::CREDENTIAL_NAMES as $name) {
            if (str_ends_with($normalized, $name)) {
                return true;
            }
        }

        return false;
    }

    public static function decodeJsonValue(mixed $value): mixed
    {
        if (is_object($value)) {
            return json_decode(json_encode($value, JSON_THROW_ON_ERROR), true, 512, JSON_THROW_ON_ERROR);
        }

        if (is_string($value)) {
            try {
                return json_decode($value, true, 512, JSON_THROW_ON_ERROR);
            } catch (JsonException) {
                // Ordinary business strings are valid setting values.
            }
        }

        return $value;
    }

    public static function redact(mixed $value): mixed
    {
        if (is_object($value)) {
            $value = self::decodeJsonValue($value);
        }

        if (! is_array($value)) {
            return $value;
        }

        foreach ($value as $key => $entry) {
            $value[$key] = self::isSensitiveKey($key) ? self::REDACTED : self::redact($entry);
        }

        return $value;
    }

    public static function assertSafeStructure(mixed $value): void
    {
        if (is_object($value)) {
            $value = self::decodeJsonValue($value);
        }

        if (! is_array($value)) {
            return;
        }

        foreach ($value as $key => $entry) {
            if (self::isSensitiveKey($key)) {
                throw new InvalidArgumentException('Credential-shaped fields cannot be stored in system settings.');
            }

            self::assertSafeStructure($entry);
        }
    }
}
