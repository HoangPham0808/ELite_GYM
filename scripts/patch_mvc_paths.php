<?php
/** Sửa đường dẫn cũ trong app/views và app/api sau migration MVC */
$root = dirname(__DIR__);
$dirs = [$root . '/app/views', $root . '/app/api'];

$replacements = [
    'header("Location: ../../../Index/Login/Login.php")' => 'header("Location: " . url("login"))',
    'header("Location: ../../../Index/Login/Login.php");' => 'header("Location: " . url("login"));',
    "header('Location: ../../Internal/Index/Login/Login.php')" => "header('Location: ' . url('login'))",
    'src="../../../Home/ELITY.png"' => 'src="<?= asset(\'images/ELITY.png\') ?>"',
    "\$dbPath = __DIR__ . '/../../../Database/db.php';\n        if (file_exists(\$dbPath)) {\n            require_once \$dbPath;\n            if (isset(\$conn) && !\$conn->connect_error) {" => "if (isset(\$conn) && !\$conn->connect_error) {",
    "require_once 'Review_Management_function.php';" => "/* AJAX via /api/admin/review */",
    "if (!empty(\$_SERVER['HTTP_X_REQUESTED_WITH']) || isset(\$_GET['ajax'])) {\n    require_once 'Review_Management_function.php';\n    exit;\n}\n\n" => '',
];

$gpsBlock = <<<'OLD'
// Load DB
$dbFile = __DIR__ . '/../../../../Database/db.php';
if (!file_exists($dbFile)) jsonOut(['ok'=>false,'msg'=>'Không tìm thấy db.php']);
require_once $dbFile;
if (!isset($conn) || $conn->connect_error) jsonOut(['ok'=>false,'msg'=>'Lỗi DB: '.($conn->connect_error??'')]);
OLD;

$gpsNew = <<<'NEW'
if (!isset($conn)) {
    $conn = Database::getInstance();
}
if ($conn->connect_error) {
    jsonOut(['ok' => false, 'msg' => 'Lỗi DB: ' . $conn->connect_error]);
}
NEW;

$webhookLog = "file_put_contents(__DIR__ . '/webhook_log.txt'";
$webhookLogNew = "file_put_contents(STORAGE_PATH . '/logs/webhook_log.txt'";

foreach ($dirs as $dir) {
    if (!is_dir($dir)) {
        continue;
    }
    $it = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS)
    );
    foreach ($it as $file) {
        if ($file->getExtension() !== 'php') {
            continue;
        }
        $path = $file->getPathname();
        $c = file_get_contents($path);
        $o = $c;
        foreach ($replacements as $from => $to) {
            $c = str_replace($from, $to, $c);
        }
        if (str_contains($path, 'GPS_function.php')) {
            $c = str_replace($gpsBlock, $gpsNew, $c);
        }
        if (str_contains($path, 'payment_webhook.php')) {
            $c = str_replace($webhookLog, $webhookLogNew, $c);
            $dbSection = <<<'DBOLD'
// ======================================================
// KẾT NỐI DATABASE
// ======================================================
$db_path = null;
$candidates = [
    // Internal/Admin/ → ELite_GYM/Database/  ← ĐÚNG cho cấu trúc hiện tại
    __DIR__ . '/../../Database/db.php',
    // Fallback các vị trí khác
    __DIR__ . '/../Database/db.php',
    __DIR__ . '/../../../Database/db.php',
    __DIR__ . '/../../../../Database/db.php',
    dirname($_SERVER['DOCUMENT_ROOT']) . '/Database/db.php',
];
foreach ($candidates as $c) {
    writeLog("Trying DB path: $c | " . (file_exists($c) ? 'FOUND' : 'not found'));
    if (file_exists($c)) { $db_path = $c; break; }
}

if (!$db_path) {
    writeLog('ERROR: db.php khong tim thay');
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'db.php not found']);
    exit;
}

writeLog("Using DB: $db_path");
require_once $db_path;

if (!isset($conn) || $conn->connect_error) {
    writeLog('ERROR: Ket noi DB that bai - ' . ($conn->connect_error ?? 'conn not set'));
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'DB connection failed']);
    exit;
}
DBOLD;
            $dbNew = <<<'DBNEW'
// ======================================================
// KẾT NỐI DATABASE
// ======================================================
if (!isset($conn)) {
    $conn = Database::getInstance();
}
if ($conn->connect_error) {
    writeLog('ERROR: Ket noi DB that bai - ' . $conn->connect_error);
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'DB connection failed']);
    exit;
}
DBNEW;
            $c = str_replace($dbSection, $dbNew, $c);
        }
        if ($c !== $o) {
            file_put_contents($path, $c);
            echo "Patched: " . str_replace($root . '/', '', $path) . "\n";
        }
    }
}

echo "Done.\n";
