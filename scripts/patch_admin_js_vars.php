<?php
/** Đổi const/let top-level → var để tránh lỗi redeclare khi load nhiều module admin */
$dir = dirname(__DIR__) . '/public/assets/js';
$skip = ['adm.js', 'legacy-ready.js', 'landing.js'];
$n = 0;
foreach (glob($dir . '/*.js') as $file) {
    if (in_array(basename($file), $skip, true)) continue;
    $c = file_get_contents($file);
    $o = $c;
    $c = preg_replace('/^const /m', 'var ', $c);
    $c = preg_replace('/^let /m', 'var ', $c);
    if ($c !== $o) {
        file_put_contents($file, $c);
        $n++;
    }
}
echo "Patched {$n} JS files in public/assets/js\n";
