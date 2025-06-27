<?php
$allowed = ['invoices', 'purchase_orders', 'proforma', 'deliveries', 'outstanding'];
$file = $_GET['file'] ?? '';

if (!in_array($file, $allowed)) {
  http_response_code(400);
  exit('❌ Invalid file requested.');
}

$path = __DIR__ . "/data/$file.json";
if (!file_exists($path)) {
  http_response_code(404);
  exit('❌ File not found.');
}

header('Content-Type: application/json');
header("Content-Disposition: attachment; filename=\"$file.json\"");
readfile($path);
exit;
?>
