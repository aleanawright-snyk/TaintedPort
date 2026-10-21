<?php
/**
 * Runtime code-integrity guard, loaded via auto_prepend_file on every PHP
 * request. It registers a shutdown handler that runs only when the request
 * ended in a fatal error; in that case it restores the backend PHP files from a
 * read-only reference copy baked into the image. Restores are per-file and
 * atomic (write a temp file, then rename() over the target), so a concurrent
 * request always reads a whole file, never a half-written one. Runtime state
 * (the SQLite database, the data/ and uploads/ directories) is never touched.
 * A healthy request registers the handler and does nothing else.
 */

register_shutdown_function(function () {
    $err = error_get_last();
    if ($err === null) {
        return;
    }
    $fatal = [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR];
    if (!in_array($err['type'], $fatal, true)) {
        return;
    }

    $pristine = '/var/www/backend-pristine';
    $live = '/var/www/backend';
    if (!is_dir($pristine) || !is_dir($live)) {
        return;
    }

    $skip = ['database.db', 'database.db-wal', 'database.db-shm'];
    $skipPrefix = ['data/', 'uploads/'];

    try {
        $it = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($pristine, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::LEAVES_ONLY
        );
    } catch (Exception $e) {
        return;
    }

    foreach ($it as $file) {
        if (!$file->isFile()) {
            continue;
        }
        $rel = substr($file->getPathname(), strlen($pristine) + 1);
        if (in_array($rel, $skip, true)) {
            continue;
        }
        foreach ($skipPrefix as $p) {
            if (strncmp($rel, $p, strlen($p)) === 0) {
                continue 2;
            }
        }

        $good = @file_get_contents($file->getPathname());
        if ($good === false) {
            continue;
        }
        $target = $live . '/' . $rel;
        if (@file_get_contents($target) === $good) {
            continue; // already matches the reference copy
        }

        $dir = dirname($target);
        if (!is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }
        $tmp = $target . '.heal.' . getmypid() . '.' . mt_rand();
        if (@file_put_contents($tmp, $good) !== false) {
            if (@rename($tmp, $target)) {
                // Drop any cached bytecode for the file we just replaced so the
                // restored version takes effect on the next request instead of
                // waiting for opcache's timestamp revalidation.
                if (function_exists('opcache_invalidate')) {
                    @opcache_invalidate($target, true);
                }
            } else {
                @unlink($tmp);
            }
        }
    }
});
