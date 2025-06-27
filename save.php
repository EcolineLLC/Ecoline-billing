<?php
header("Content-Type: application/json");

$data = json_decode(file_get_contents("php://input"), true);
$type = $data['type'] ?? '';
$entry = $data['data'] ?? [];

$files = [
  "invoice" => "data/invoices.json",
  "purchase" => "data/purchase_orders.json",
  "proforma" => "data/proforma.json",
  "delivery" => "data/deliveries.json",
  "outstanding" => "data/outstanding.json"
];

if (!isset($files[$type])) {
  echo json_encode(["status" => "error", "message" => "Invalid type"]);
  exit;
}

$file = __DIR__ . '/' . $files[$type];
if (!file_exists($file)) file_put_contents($file, "[]");

$existing = json_decode(file_get_contents($file), true);
$existing[] = $entry;

file_put_contents($file, json_encode($existing, JSON_PRETTY_PRINT));
echo json_encode(["status" => "success"]);
