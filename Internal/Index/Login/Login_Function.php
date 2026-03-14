<?php
ob_start(); // Prevent "headers already sent" error

// ── [1] "Remember me" cookie BEFORE session_start() ───────────────────────────────
if (isset($_POST['remember_me'])) {
    $lifetime = 30 * 24 * 60 * 60;
    ini_set('session.cookie_lifetime', $lifetime);
    ini_set('session.gc_maxlifetime',  $lifetime);
    session_set_cookie_params([
        'lifetime' => $lifetime,
        'path'     => '/',
        'secure'   => false,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
}

session_start();

// Directory structure:
//   DATN/Internal/Index/Login/Login_Function.php
//   DATN/Layout/Customer_Management/
// From Login/ need to go up 3 levels (../../../) to reach DATN/

// ── [2] Already logged in → go to main page ─────────────────────────────────────
if (isset($_SESSION['account_id'])) {
    $role = $_SESSION['role'] ?? '';
    $position_session = $_SESSION['position'] ?? '';

    if ($role === 'Customer') {
        header("Location: ../../../Home/index.php");
    } elseif ($role === 'Admin') {
        header("Location: ../../Admin/adm.php");
    } elseif ($role === 'Employee') {
        if ($position_session === 'Receptionist') {
            header("Location: ../../Staff/Receptionist/Receptionist.php");
        } elseif ($position_session === 'Personal Trainer') {
            header("Location: ../../Staff/HLV/HLV.php");
        }
    } else {
        header("Location: ../../Admin/adm.php");
    }
    exit;
}

// ── [3] Only accept POST ─────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: Login.php");
    exit;
}

// ── [4] Connect to DB ────────────────────────────────────────────────────────────
require_once __DIR__ . '/../../../Database/db.php';

if (!isset($conn) || $conn->connect_error) {
    error_log("DB connect error: " . ($conn->connect_error ?? 'conn not defined'));
    header("Location: Login.php?error=database_error");
    exit;
}

// ── [5] Input ───────────────────────────────────────────────────────────────
$username = trim($_POST['username'] ?? '');
$password = $_POST['password'] ?? '';

if ($username === '' || $password === '') {
    header("Location: Login.php?error=empty_fields");
    exit;
}

if (strlen($username) < 3 || strlen($password) < 6) {
    header("Location: Login.php?error=invalid_credentials");
    exit;
}

// ── [6] Query account ───────────────────────────────────────────────────
$stmt = $conn->prepare("
    SELECT account_id, username, password, role, is_active
    FROM Account
    WHERE username = ?
    LIMIT 1
");

if (!$stmt) {
    error_log("Prepare failed: " . $conn->error);
    header("Location: Login.php?error=database_error");
    exit;
}

$stmt->bind_param("s", $username);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    $stmt->close();
    header("Location: Login.php?error=invalid_credentials");
    exit;
}

$user = $result->fetch_assoc();
$stmt->close();

// ── [7] Account status ─────────────────────────────────────────────────
if (!(bool)$user['is_active']) {
    header("Location: Login.php?error=account_disabled");
    exit;
}

// ── [8] Authorization: Admin, Employee and Customer ─────────────────────────────
if (!in_array($user['role'], ['Admin', 'Employee', 'Customer'], true)) {
    header("Location: Login.php?error=invalid_credentials");
    exit;
}

// ── [9] Verify password ────────────────────────────────────────────────────
if (str_starts_with($user['password'], '$')) {
    $password_valid = password_verify($password, $user['password']);
} else {
    $password_valid = hash_equals($user['password'], $password);
}

if (!$password_valid) {
    header("Location: Login.php?error=invalid_credentials");
    exit;
}

// ── [10] Get full_name + position from Employee table ──────────────────────────
$full_name  = $user['username']; // fallback
$position = null;

if ($user['role'] === 'Employee') {
    // Employee → get full_name AND position from Employee table
    $stmt2 = $conn->prepare("SELECT full_name, position FROM Employee WHERE account_id = ? LIMIT 1");
    if ($stmt2) {
        $stmt2->bind_param("i", $user['account_id']);
        $stmt2->execute();
        $r2 = $stmt2->get_result();
        if ($row2 = $r2->fetch_assoc()) {
            $full_name = $row2['full_name'];
            $position  = $row2['position']; // 'Receptionist' or 'Personal Trainer'
        }
        $stmt2->close();
    }
} elseif ($user['role'] === 'Admin') {
    // Admin → get full_name only, position MUST stay null to avoid wrong redirect
    $stmt2 = $conn->prepare("SELECT full_name FROM Employee WHERE account_id = ? LIMIT 1");
    if ($stmt2) {
        $stmt2->bind_param("i", $user['account_id']);
        $stmt2->execute();
        $r2 = $stmt2->get_result();
        if ($row2 = $r2->fetch_assoc()) {
            $full_name = $row2['full_name'];
        }
        $stmt2->close();
    }
} else {
    // Customer → get full_name from Customer table
    $stmt2 = $conn->prepare("SELECT full_name FROM Customer WHERE account_id = ? LIMIT 1");
    if ($stmt2) {
        $stmt2->bind_param("i", $user['account_id']);
        $stmt2->execute();
        $r2 = $stmt2->get_result();
        if ($row2 = $r2->fetch_assoc()) {
            $full_name = $row2['full_name'];
        }
        $stmt2->close();
    }
}

// ── [11] Create session ─────────────────────────────────────────────────────────
// Use false instead of true to not delete old session immediately
// avoid race condition on WAMP/localhost
session_regenerate_id(false);

$_SESSION['account_id']  = (int)$user['account_id'];
$_SESSION['username'] = $user['username'];
$_SESSION['role']       = $user['role'];
$_SESSION['full_name']        = $full_name;
$_SESSION['position']       = $position;
$_SESSION['login_time']    = time();
$_SESSION['ip_address']    = $_SERVER['REMOTE_ADDR'] ?? '';

// ── [12] Log login (skip if table doesn't exist) ──────────────────────────────
// Correct column names per schema: login_time, ip_address, result
try {
    $ip        = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    $ua        = $_SERVER['HTTP_USER_AGENT'] ?? '';
    $result   = 'Success';
    $stmtL = $conn->prepare("
        INSERT INTO LoginHistory (account_id, login_time, ip_address, user_agent, result)
        VALUES (?, NOW(), ?, ?, ?)
    ");
    if ($stmtL) {
        $stmtL->bind_param("isss", $_SESSION['account_id'], $ip, $ua, $result);
        $stmtL->execute();
        $stmtL->close();
    }
} catch (Throwable $e) {
    error_log("Log login: " . $e->getMessage());
}

$conn->close();

// ── [13] Redirect by role & position ─────────────────────────────────────
// Directory structure:
//   This file: DATN/Internal/Index/Login/Login_Function.php
//   From Login/ up 2 levels (../../) = DATN/Internal/
//   From Login/ up 3 levels (../../../) = DATN/

switch ($user['role']) {

    case 'Customer':
        // DATN/Internal/Index/Login/ → ../../../User/index.html
        header("Location: ../../../Home/index.php");
        break;

    case 'Admin':
        // DATN/Internal/Index/Login/ → ../../Admin/adm.php
        header("Location: ../../Admin/adm.php");
        break;

    case 'Employee':
        // Divide by position
        if ($position === 'Receptionist') {
            // DATN/Internal/Index/Login/ → ../../Staff/Receptionist/Receptionist.php
            header("Location: ../../Staff/Receptionist/Receptionist.php");
        } elseif ($position === 'Personal Trainer') {
            // DATN/Internal/Index/Login/ → ../../Staff/HLV/HLV.php
            header("Location: ../../Staff/HLV/HLV.php");
        } else {
            // Employee without position → fallback to admin page
            header("Location: ../../Admin/adm.php");
        }
        break;

    default:
        header("Location: Login.php?error=invalid_credentials");
        break;
}
exit;
?>
