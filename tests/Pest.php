<?php

use MarcoKoepfli\LaravelPatrol\Tests\TestCase;

uses(TestCase::class)->in(__DIR__);

/**
 * Recursively delete a directory and its contents.
 */
function cleanDir(string $dir): void
{
    if (! is_dir($dir)) {
        return;
    }
    $files = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    );
    foreach ($files as $file) {
        $file->isDir() ? rmdir($file->getRealPath()) : unlink($file->getRealPath());
    }
    rmdir($dir);
}
