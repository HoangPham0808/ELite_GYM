<?php

function ensureUploadDirs(): void
{
    foreach ([PKG_UPLOAD_DIR, PANEL_UPLOAD_DIR] as $dir) {
        if (!is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }
    }
}

function uploadUrl(string $subdir, string $filename): string
{
    return UPLOAD_URL . '/' . trim($subdir, '/') . '/' . ltrim($filename, '/');
}
