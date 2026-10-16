<?php
/**
 * BSD API: Admin Authentication
 */

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');

require_once __DIR__ . '/../config.php';

$action = $_GET['action'] ?? ($_POST['action'] ?? 'check');

if ($action === 'check') {
    echo json_encode([
        'authenticated' => isBimAdmin(),
        'user'          => $_SESSION['full_name'] ?? ($_SESSION['user'] ?? 'BIM Administrator'),
        'role'          => $_SESSION['role'] ?? (isBimAdmin() ? 'admin' : 'guest')
    ]);
    exit;
}

if ($action === 'login') {
    $raw = file_get_contents('php://input');
    $data = json_decode($raw, true) ?: $_POST;
    $password = $data['password'] ?? '';

    $configuredPass = defined('CUSTOM_ADMIN_PASSWORD') ? CUSTOM_ADMIN_PASSWORD : DEFAULT_ADMIN_PASSWORD;

    if ($password === $configuredPass) {
        $_SESSION['bim_admin_auth'] = true;
        echo json_encode(['success' => true, 'message' => 'Authenticated successfully']);
    } else {
        http_response_code(401);
        echo json_encode(['success' => false, 'error' => 'Incorrect password']);
    }
    exit;
}

if ($action === 'logout') {
    unset($_SESSION['bim_admin_auth']);
    echo json_encode(['success' => true, 'message' => 'Logged out successfully']);
    exit;
}

http_response_code(400);
echo json_encode(['error' => 'Invalid action']);
exit;
