<?php
$dirs = [
    dirname(__DIR__) . '/app/modules/admin',
    dirname(__DIR__) . '/public/assets/js',
];
$n = 0;
foreach ($dirs as $dir) {
    if (!is_dir($dir)) continue;
    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS));
    foreach ($it as $file) {
        if ($file->getExtension() !== 'js') continue;
        $c = file_get_contents($file->getPathname());
        $o = $c;
        $c = str_replace('document.addEventListener(\'DOMContentLoaded\',', 'runWhenReady(', $c);
        $c = str_replace('document.addEventListener("DOMContentLoaded",', 'runWhenReady(', $c);
        $c = str_replace('window.addEventListener(\'DOMContentLoaded\',', 'runWhenReady(', $c);
        if ($c !== $o) {
            file_put_contents($file->getPathname(), $c);
            $n++;
        }
    }
}
echo "DOM ready patched: {$n}\n";
