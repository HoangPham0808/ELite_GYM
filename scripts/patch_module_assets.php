<?php
$root = dirname(__DIR__) . '/app/modules';
$it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, RecursiveDirectoryIterator::SKIP_DOTS));
$n = 0;
foreach ($it as $file) {
    if ($file->getExtension() !== 'php') continue;
    $c = file_get_contents($file->getPathname());
    $o = $c;
    // href="Module.css" → asset
    $c = preg_replace('/href="([A-Za-z0-9_]+\.css)"/', "href=\"<?= asset('css/$1') ?>\"", $c);
    $c = preg_replace('/src="([A-Za-z0-9_]+\.js)"/', "src=\"<?= asset('js/$1') ?>\"", $c);
    // Subfolder js like QZ_Check/QR_CheckIn.js
    $c = preg_replace('/src="([A-Za-z0-9_\/]+\.js)"/', function ($m) {
        $name = basename($m[1]);
        return 'src="<?= asset(\'js/' . $name . '\') ?>"';
    }, $c);
    if ($c !== $o) {
        file_put_contents($file->getPathname(), $c);
        $n++;
    }
}
echo "Module assets patched: {$n}\n";
