<?php
// Reset all JSON files if reset is triggered
if (isset($_GET['reset']) && $_GET['reset'] === 'true') {
  foreach (['invoices.json', 'purchase_orders.json', 'proforma.json', 'deliveries.json', 'outstanding.json'] as $file) {
    file_put_contents(__DIR__ . "/data/$file", json_encode([], JSON_PRETTY_PRINT));
  }
  header("Location: dashboard.php");
  exit;
}

function loadData($filename) {
  $path = __DIR__ . "/data/$filename";
  if (!file_exists($path)) return [];
  $json = file_get_contents($path);
  $data = json_decode($json, true);
  return is_array($data) ? $data : [];
}

// Load data
$invoices = loadData("invoices.json");
$purchaseOrders = loadData("purchase_orders.json");
$proformas = loadData("proforma.json");
$deliveries = loadData("deliveries.json");
$outstanding = loadData("outstanding.json");

// Totals
$invoiceTotal = $invoiceVAT = 0;
foreach ($invoices as $inv) {
  $invoiceTotal += floatval($inv['total']);
  $invoiceVAT   += floatval($inv['vat']);
}

$poTotal = $poVAT = 0;
foreach ($purchaseOrders as $po) {
  $poTotal += floatval($po['total']);
  $poVAT   += floatval($po['vat']);
}

$vatPayable = $invoiceVAT - $poVAT;

// Filters
$searchCustomer = trim($_GET['customer'] ?? '');
$filteredOutstanding = array_filter($outstanding, function($entry) use ($searchCustomer) {
  return $searchCustomer === '' || stripos($entry['customer'], $searchCustomer) !== false;
});
$totalFilteredOutstanding = array_sum(array_column($filteredOutstanding, 'amount'));

// Invoice date filter
$start = $_GET['from'] ?? '';
$end   = $_GET['to'] ?? '';
$filteredInvoices = array_filter($invoices, function ($inv) use ($start, $end) {
  $date = $inv['date'];
  if ($start && $date < $start) return false;
  if ($end && $date > $end) return false;
  return true;
});

// Outstanding grouped by customer
$grouped = [];
foreach ($outstanding as $o) {
  $c = $o['customer'];
  if (!isset($grouped[$c])) $grouped[$c] = 0;
  $grouped[$c] += $o['amount'];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta http-equiv="refresh" content="60">
  <title>Ecoline LLC - Business Dashboard</title>
  <script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
  <style>
    body { font-family: Arial; background: #f0f2f5; padding: 30px; }
    h1, h2, h3 { color: #004080; }
    table { width: 100%; border-collapse: collapse; margin-top: 20px; background: white; }
    th, td { padding: 10px; border: 1px solid #ccc; text-align: center; }
    th { background: #003366; color: white; }
    tr:nth-child(even) { background: #f2f2f2; }
    .summary, .cards { background: white; padding: 20px; margin-top: 30px; border-radius: 10px; box-shadow: 0 0 10px rgba(0,0,0,0.1); }
    .summary p { font-size: 18px; margin: 10px 0; }
    .highlight { color: #b30000; font-weight: bold; font-size: 20px; }
    .cards { display: flex; gap: 20px; justify-content: space-between; flex-wrap: wrap; }
    .card { flex: 1 1 250px; text-align: center; border-right: 1px solid #ddd; padding: 10px; }
    .card:last-child { border-right: none; }
    .reset-button { background:red; color:white; padding:8px 16px; border:none; border-radius:4px; margin-top:10px; }
    .export-link { font-weight: bold; text-decoration:none; padding: 8px; background: #1b3a57; color: #fff; border-radius: 4px; }
  </style>
</head>
<body>

<h1>📊 Ecoline LLC - Business Dashboard</h1>

<form method="get" onsubmit="return confirm('Are you sure you want to reset all data?');">
  <button type="submit" name="reset" value="true" class="reset-button">🔄 Reset All Data</button>
</form>

<!-- Cards -->
<div class="cards">
  <div class="card"><h3>Total Invoices</h3><p><?= count($invoices) ?></p></div>
  <div class="card"><h3>Total POs</h3><p><?= count($purchaseOrders) ?></p></div>
  <div class="card"><h3>Total Deliveries</h3><p><?= count($deliveries) ?></p></div>
  <div class="card"><h3>Total Outstanding</h3><p>AED <?= number_format(array_sum(array_column($outstanding, 'amount')), 2) ?></p></div>
</div>

<!-- Pie Chart -->
<h2>💰 VAT Comparison</h2>
<canvas id="vatChart" width="300" height="180" style="max-width: 300px;"></canvas>
<script>
const ctx = document.getElementById('vatChart').getContext('2d');
new Chart(ctx, {
  type: 'pie',
  data: {
    labels: ['Invoice VAT', 'PO VAT'],
    datasets: [{
      data: [<?= $invoiceVAT ?>, <?= $poVAT ?>],
      backgroundColor: ['#007bff', '#ffc107']
    }]
  },
  options: {
    responsive: false,
    plugins: { legend: { position: 'bottom' } }
  }
});
</script>

<!-- Invoice Table -->
<h2>🧾 Tax Invoices</h2>
<form method="get">
  <label>From: <input type="date" name="from" value="<?= htmlspecialchars($start) ?>"></label>
  <label>To: <input type="date" name="to" value="<?= htmlspecialchars($end) ?>"></label>
  <button type="submit">Filter</button>
</form>
<input type="text" id="invoiceSearch" placeholder="🔍 Search invoices..." style="padding:6px; font-size:14px; width:100%; max-width:400px; margin-top:10px;">
<button onclick="exportTableToExcel('invoiceTable', 'invoices')">Export Invoices</button>
<table id="invoiceTable">
  <thead><tr><th>Invoice No</th><th>Date</th><th>Customer</th><th>Grand Total</th><th>VAT Total</th></tr></thead>
  <tbody>
    <?php foreach ($filteredInvoices as $inv): ?>
    <tr>
      <td><?= htmlspecialchars($inv['invoiceNo']) ?></td>
      <td><?= htmlspecialchars($inv['date']) ?></td>
      <td><?= htmlspecialchars($inv['customer'] ?? '') ?></td>
      <td><?= number_format($inv['total'], 2) ?></td>
      <td><?= number_format($inv['vat'], 2) ?></td>
    </tr>
    <?php endforeach; ?>
  </tbody>
</table>

<!-- Purchase Orders -->
<h2>📋 Purchase Orders</h2>
<table>
  <thead><tr><th>PO No</th><th>Date</th><th>Customer</th><th>Grand Total</th><th>VAT Total</th></tr></thead>
  <tbody>
    <?php foreach ($purchaseOrders as $po): ?>
    <tr>
      <td><?= htmlspecialchars($po['poNo']) ?></td>
      <td><?= htmlspecialchars($po['date']) ?></td>
      <td><?= htmlspecialchars($po['customer'] ?? '') ?></td>
      <td><?= number_format($po['total'], 2) ?></td>
      <td><?= number_format($po['vat'], 2) ?></td>
    </tr>
    <?php endforeach; ?>
  </tbody>
</table>

<!-- Proforma -->
<h2>📃 Proforma Invoices</h2>
<table>
  <thead><tr><th>Proforma No</th><th>Date</th><th>Customer</th><th>Grand Total</th><th>VAT</th></tr></thead>
  <tbody>
    <?php foreach ($proformas as $p): ?>
    <tr>
      <td><?= htmlspecialchars($p['proformaNo']) ?></td>
      <td><?= htmlspecialchars($p['date']) ?></td>
      <td><?= htmlspecialchars($p['customer']) ?></td>
      <td><?= number_format($p['total'], 2) ?></td>
      <td><?= number_format($p['vat'], 2) ?></td>
    </tr>
    <?php endforeach; ?>
  </tbody>
</table>

<!-- Deliveries -->
<h2>📦 Delivery Summary</h2>
<p>Total Deliveries Made: <strong><?= count($deliveries) ?></strong></p>

<!-- Outstanding -->
<h2>📑 Outstanding Statement</h2>
<form method="get">
  <input type="text" name="customer" placeholder="Search by customer name" value="<?= htmlspecialchars($searchCustomer) ?>" style="padding:6px; width:300px;">
  <button type="submit">Search</button>
  <?php if ($searchCustomer): ?><a href="dashboard.php" style="margin-left: 10px;">Reset</a><?php endif; ?>
</form>
<table>
  <thead><tr><th>Customer</th><th>Date</th><th>Outstanding Amount</th></tr></thead>
  <tbody>
    <?php foreach ($filteredOutstanding as $entry): ?>
    <tr>
      <td><?= htmlspecialchars($entry['customer']) ?></td>
      <td><?= htmlspecialchars($entry['date']) ?></td>
      <td><?= number_format($entry['amount'], 2) ?></td>
    </tr>
    <?php endforeach; ?>
  </tbody>
</table>
<p><strong>Total Outstanding<?= $searchCustomer ? " for <u>" . htmlspecialchars($searchCustomer) . "</u>" : "" ?>:</strong> AED <?= number_format($totalFilteredOutstanding, 2) ?></p>

<!-- Summary by Customer -->
<h3>🧾 Outstanding Summary by Customer</h3>
<table>
  <thead><tr><th>Customer</th><th>Total Outstanding</th></tr></thead>
  <tbody>
    <?php foreach ($grouped as $customer => $amount): ?>
    <tr>
      <td><?= htmlspecialchars($customer) ?></td>
      <td>AED <?= number_format($amount, 2) ?></td>
    </tr>
    <?php endforeach; ?>
  </tbody>
</table>

<!-- Grand Totals -->
<div class="summary">
  <p><strong>Total Invoice Amount:</strong> AED <?= number_format($invoiceTotal, 2) ?></p>
  <p><strong>Total Invoice VAT:</strong> AED <?= number_format($invoiceVAT, 2) ?></p>
  <p><strong>Total PO Amount:</strong> AED <?= number_format($poTotal, 2) ?></p>
  <p><strong>Total PO VAT:</strong> AED <?= number_format($poVAT, 2) ?></p>
  <p class="highlight"><strong>VAT Payable to Govt:</strong> AED <?= number_format($vatPayable, 2) ?></p>
</div>

<!-- Download links -->
<h3>📥 Download Individual JSON Files</h3>
<ul>
  <li><a href="download.php?file=invoices" target="_blank">🧾 Download Invoices</a></li>
  <li><a href="download.php?file=purchase_orders" target="_blank">📋 Download Purchase Orders</a></li>
  <li><a href="download.php?file=proforma" target="_blank">📃 Download Proforma Invoices</a></li>
  <li><a href="download.php?file=deliveries" target="_blank">📦 Download Delivery Notes</a></li>
  <li><a href="download.php?file=outstanding" target="_blank">📑 Download Outstanding Statements</a></li>
</ul>

<script>
// Export to Excel
function exportTableToExcel(tableID, filename = '') {
  const table = document.getElementById(tableID);
  const wb = XLSX.utils.table_to_book(table, { sheet: "Sheet1" });
  XLSX.writeFile(wb, filename + ".xlsx");
}

// Live search filter
document.getElementById('invoiceSearch').addEventListener('input', function () {
  const val = this.value.toLowerCase();
  document.querySelectorAll("#invoiceTable tbody tr").forEach(row => {
    const text = row.innerText.toLowerCase();
    row.style.display = text.includes(val) ? '' : 'none';
  });
});
</script>

</body>
</html>
