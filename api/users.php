<?php
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/functions.php';

echo json_encode(getProgressStore(), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
exit;
