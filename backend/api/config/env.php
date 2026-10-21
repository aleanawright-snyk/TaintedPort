<?php

/**
 * Minimal .env loader. Values come from the real environment first (so a
 * runtime `-e VAR=...` always wins); otherwise they are read from
 * backend/.env, which is gitignored but baked into the Docker image.
 */
class Env {
    private static $loaded = false;
    private static $vars = [];

    private static function load() {
        if (self::$loaded) return;
        self::$loaded = true;
        $path = __DIR__ . '/../../.env';
        if (!is_readable($path)) return;
        foreach (file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
            $line = trim($line);
            if ($line === '' || $line[0] === '#') continue;
            $eq = strpos($line, '=');
            if ($eq === false) continue;
            $key = trim(substr($line, 0, $eq));
            $val = trim(substr($line, $eq + 1));
            $len = strlen($val);
            if ($len >= 2 && ($val[0] === '"' || $val[0] === "'") && $val[$len - 1] === $val[0]) {
                $val = substr($val, 1, -1);
            }
            self::$vars[$key] = $val;
        }
    }

    public static function get($key, $default = '') {
        $env = getenv($key);
        if ($env !== false && $env !== '') return $env;
        self::load();
        return isset(self::$vars[$key]) ? self::$vars[$key] : $default;
    }

    /**
     * Fetch a required value, enforcing a length constraint. Returns the value
     * only when it is present and correctly sized; otherwise responds with a
     * 500 and stops, so a missing/misconfigured value can never fall back to a
     * built-in default. Pass $exactLen for a fixed length, otherwise $minLen.
     */
    public static function requireValue($key, $minLen = 0, $exactLen = null) {
        $v = self::get($key, '');
        $ok = is_string($v) && $v !== '';
        if ($ok) {
            $len = strlen($v);
            $ok = ($exactLen !== null) ? ($len === $exactLen) : ($len >= $minLen);
        }
        if (!$ok) {
            http_response_code(500);
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Server configuration error.']);
            exit;
        }
        return $v;
    }
}
