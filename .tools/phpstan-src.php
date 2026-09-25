<?php

declare(strict_types=1);

/**
 * Unpacks phpstan.phar into .var/phpstan-src so the editor can see
 * PHPStan's classes (Intelephense can't read inside a .phar).
 *
 * Runs on every container start, but only unpacks when the phar has
 * changed since last time, so a normal start costs almost nothing.
 */

$root = dirname(__DIR__);
$phar = "{$root}/vendor/phpstan/phpstan/phpstan.phar";
$dest = "{$root}/.var/phpstan-src";
$stamp = "{$dest}/.phar-sha256";

if (!is_file($phar)) {
    fwrite(STDERR, "phpstan-src: no phpstan.phar yet, run composer install first\n");
    exit(0); // not an error: nothing to unpack
}

$hash = hash_file('sha256', $phar);

if (is_file($stamp) && file_get_contents($stamp) === $hash) {
    exit(0); // already up to date
}

// Old copy from a previous PHPStan version: remove it first.
if (is_dir($dest)) {
    $files = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($dest, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST,
    );
    foreach ($files as $file) {
        $file->isDir() ? rmdir($file->getPathname()) : unlink($file->getPathname());
    }
    rmdir($dest);
}

(new Phar($phar))->extractTo($dest);
file_put_contents($stamp, $hash);

echo "phpstan-src: unpacked into .var/phpstan-src\n";
