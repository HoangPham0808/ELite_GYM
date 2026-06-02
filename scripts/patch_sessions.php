<?php
$root = dirname(__DIR__) . '/app/modules';
$it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, RecursiveDirectoryIterator::SKIP_DOTS));
$n = 0;
foreach ($it as $file) {
    if ($file->getExtension() !== 'php') continue;
    $path = $file->getPathname();
    if (str_contains($path, 'Login_Function.php')) continue; // xử lý cookie riêng
    $c = file_get_contents($path);
    if (!str_contains($c, 'session_start()')) continue;
    $o = $c;
    $c = preg_replace('/^\s*session_start\(\);\s*$/m', 'ensureSession();', $c);
    if ($c !== $o) {
        file_put_contents($path, $c);
        $n++;
    }
}
echo "Sessions patched: {$n}\n";
