<?php

require_once __DIR__ . '/env.php';

/**
 * Signing helper for shareable "track your order" links. The signature is a
 * SHA-256 keyed with a standalone secret, over the raw token bytes.
 */
class TrackingLink {
    private static function secret() {
        return Env::requireValue('TRACKING_LINK_SECRET', 0, 14);
    }

    public static function sign($data) {
        return hash('sha256', self::secret() . $data);
    }

    public static function verify($data, $sig) {
        return is_string($sig) && hash_equals(self::sign($data), $sig);
    }
}
