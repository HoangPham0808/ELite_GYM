<?php
$root = dirname(__DIR__);
$dirs = [$root . '/app/modules', $root . '/public/assets/js'];
$n = 0;
foreach ($dirs as $dir) {
    if (!is_dir($dir)) continue;
    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS));
    foreach ($it as $file) {
        if ($file->getExtension() !== 'js') continue;
        $c = file_get_contents($file->getPathname());
        $o = $c;
        // const API = 'window.ELITE_BASE + '/path'';
        $c = preg_replace(
            "/(const\s+(?:API(?:_URL)?)\s*=\s*)'window\.ELITE_BASE\s*\+\s*'([^']+)'';/",
            '$1window.ELITE_BASE + \'$2\';',
            $c
        );
        $c = preg_replace(
            "/fetch\('window\.ELITE_BASE\s*\+\s*'([^']+)''/",
            "fetch(window.ELITE_BASE + '$1'",
            $c
        );
        $c = preg_replace(
            "/(const\s+API\s*=\s*)'window\.ELITE_BASE\s*\+\s*'([^']+)'';/",
            '$1window.ELITE_BASE + \'$2\';',
            $c
        );
        $c = preg_replace(
            "/(const\s+API\s+\s*=\s*)'window\.ELITE_BASE\s*\+\s*'([^']+)'';/",
            '$1window.ELITE_BASE + \'$2\';',
            $c
        );
        if ($c !== $o) {
            file_put_contents($file->getPathname(), $c);
            $n++;
        }
    }
}
echo "Fixed {$n} JS files\n";
