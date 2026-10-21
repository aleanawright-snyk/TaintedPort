<?php

require_once __DIR__ . '/env.php';

/**
 * Gift-card / store-credit tokens. A card's value travels inside an
 * AES-128-CBC blob (IV prepended), base64-encoded.
 */
class GiftCard {
    private static function key() {
        return Env::requireValue('GIFTCARD_AES_KEY', 0, 16);
    }

    public static function issue($amount, $id) {
        $pt = sprintf('amount=%08.2f&id=%s', $amount, $id);
        $iv = random_bytes(16);
        $ct = openssl_encrypt($pt, 'aes-128-cbc', self::key(), OPENSSL_RAW_DATA, $iv);
        return base64_encode($iv . $ct);
    }

    public static function decode($token) {
        $raw = base64_decode($token, true);
        if ($raw === false || strlen($raw) < 32) {
            return null;
        }
        $iv = substr($raw, 0, 16);
        $ct = substr($raw, 16);
        $pt = openssl_decrypt($ct, 'aes-128-cbc', self::key(), OPENSSL_RAW_DATA, $iv);
        if ($pt === false) {
            return null;
        }
        parse_str($pt, $fields);
        return $fields;
    }
}
