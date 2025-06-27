<?php
$data = json_decode(file_get_contents('php://input'), true);
if (!$data) { echo json_encode(['status' => 'error', 'message' => 'Invalid data']); exit; }

$file = __DIR__ . '/data/purchase_orders.json';
$existing = file_exists($file) ? json_decode(file_get_contents($file), true) : [];
$existing[] = $data;

file_put_contents($file, json_encode($existing, JSON_PRETTY_PRINT));
echo json_encode(['status' => 'success']);
?>
