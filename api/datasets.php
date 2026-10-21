<?php
/**
 * BSD API: Datasets Manager (List & Delete)
 */

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');

require_once __DIR__ . '/../config.php';

$jsonFile = defined('DATASETS_JSON_FILE') ? DATASETS_JSON_FILE : (__DIR__ . '/../datasets.json');
$datasetsDir = defined('DATASETS_DIR') ? DATASETS_DIR : (__DIR__ . '/../datasets');

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $list = [];
    if (file_exists($jsonFile)) {
        $list = json_decode(file_get_contents($jsonFile), true) ?: [];
    }
    echo json_encode($list);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true) ?: $_POST;
    $action = $input['action'] ?? '';
    
    if ($action === 'delete') {
        $id = $input['id'] ?? '';
        if (!$id) {
            http_response_code(400);
            echo json_encode(['error' => 'Missing dataset ID']);
            exit;
        }

        $existing = [];
        if (file_exists($jsonFile)) {
            $existing = json_decode(file_get_contents($jsonFile), true) ?: [];
        }

        $found = null;
        $updated = [];
        foreach ($existing as $item) {
            if ($item['id'] === $id) {
                $found = $item;
            } else {
                $updated[] = $item;
            }
        }

        if ($found && !empty($found['filename'])) {
            $filePath = $datasetsDir . DIRECTORY_SEPARATOR . $found['filename'];
            if (file_exists($filePath)) {
                @unlink($filePath);
            }
        }

        file_put_contents($jsonFile, json_encode($updated, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        echo json_encode(['success' => true, 'remaining' => count($updated)]);
        exit;
    }
}

http_response_code(400);
echo json_encode(['error' => 'Invalid request']);
