<?php
session_start();

// Include database connection
require_once '../../../Database/db.php';

// Check if form was submitted via POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: Register.php");
    exit;
}

// Get and validate input
$username         = isset($_POST['username'])         ? trim($_POST['username'])         : '';
$email            = isset($_POST['email'])            ? trim($_POST['email'])            : '';
$fullname         = isset($_POST['full_name'])        ? trim($_POST['full_name'])        : '';
$phone            = isset($_POST['phone'])            ? trim($_POST['phone'])            : '';
$password         = isset($_POST['password'])         ? $_POST['password']               : '';
$confirm_password = isset($_POST['confirm_password']) ? $_POST['confirm_password']       : '';
$accept_terms     = isset($_POST['accept_terms'])     ? true                             : false;

// Input validation
if (empty($username) || empty($email) || empty($fullname) || empty($password) || empty($confirm_password)) {
    header("Location: Register.php?error=empty_fields");
    exit;
}

if (!$accept_terms) {
    header("Location: Register.php?error=terms_not_accepted");
    exit;
}

// Validate username format
if (strlen($username) < 3 || strlen($username) > 50) {
    header("Location: Register.php?error=invalid_username");
    exit;
}

if (!preg_match('/^[a-zA-Z0-9_]+$/', $username)) {
    header("Location: Register.php?error=invalid_username");
    exit;
}

// Validate email
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    header("Location: Register.php?error=invalid_email");
    exit;
}

// Validate fullname
if (strlen($fullname) < 3 || strlen($fullname) > 150) {
    header("Location: Register.php?error=invalid_fullname");
    exit;
}

// Validate phone (optional but if provided must be valid)
if (!empty($phone)) {
    if (!preg_match('/^(0|\+84)[0-9]{9,10}$/', str_replace(' ', '', $phone))) {
        header("Location: Register.php?error=invalid_phone");
        exit;
    }
}

// Validate password
if (strlen($password) < 6) {
    header("Location: Register.php?error=weak_password");
    exit;
}

// Check if passwords match
if ($password !== $confirm_password) {
    header("Location: Register.php?error=password_mismatch");
    exit;
}

try {
    // Check if username already exists in Account table
    $stmt = $conn->prepare("SELECT account_id FROM Account WHERE username = ?");

    if (!$stmt) {
        throw new Exception("Prepare failed: " . $conn->error);
    }

    $stmt->bind_param("s", $username);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $stmt->close();
        header("Location: Register.php?error=username_exists");
        exit;
    }

    $stmt->close();

    // Check if email already exists in Customer table
    $stmt = $conn->prepare("SELECT customer_id FROM Customer WHERE email = ?");

    if (!$stmt) {
        throw new Exception("Prepare failed: " . $conn->error);
    }

    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $stmt->close();
        header("Location: Register.php?error=email_exists");
        exit;
    }

    $stmt->close();

    // Hash password


    // Insert into Account with role 'Customer'
    $role      = 'Customer';
    $is_active = true;

    $stmt = $conn->prepare("INSERT INTO Account (username, password, role, is_active) VALUES (?, ?, ?, ?)");

    if (!$stmt) {
        throw new Exception("Prepare failed: " . $conn->error);
    }

    $stmt->bind_param("sssi", $username, $password, $role, $is_active);
    $stmt->execute();

    if ($stmt->affected_rows === 0) {
        throw new Exception("Failed to create account");
    }

    $account_id = $stmt->insert_id;
    $stmt->close();

    // Insert into Customer
    $registered_at = date('Y-m-d');

    $stmt = $conn->prepare("INSERT INTO Customer (full_name, email, phone, registered_at, account_id) 
                            VALUES (?, ?, ?, ?, ?)");

    if (!$stmt) {
        throw new Exception("Prepare failed: " . $conn->error);
    }

    $stmt->bind_param("ssssi", $fullname, $email, $phone, $registered_at, $account_id);
    $stmt->execute();
    $stmt->close();

    // Registration successful
    header("Location: Register.php?success=1");
    exit;

} catch (Exception $e) {
    // Log error
    error_log("Registration error: " . $e->getMessage());
    header("Location: Register.php?error=database_error");
    exit;
}

// Cleanup
$conn->close();
?>
