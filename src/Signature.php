<?php

declare(strict_types=1);

namespace Peccancy\Partner;

/**
 * HMAC-SHA256 signing primitives, matching the Peccancy platform's scheme.
 * You normally don't call these directly — PartnerClient does it for you.
 */
final class Signature
{
    /** Lowercase hex HMAC-SHA256 of $payload keyed by $secret. */
    public static function sign(string $payload, string $secret): string
    {
        return hash_hmac('sha256', $payload, $secret);
    }

    /** Constant-time comparison of two hex signatures. */
    public static function equals(string $a, string $b): bool
    {
        return hash_equals($a, $b);
    }
}
