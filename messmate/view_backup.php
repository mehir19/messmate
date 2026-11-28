<?php
require 'db.php';

if (!isset($_SESSION['user_id'], $_SESSION['mess_id'])) {
    header("Location: login.php");
    exit;
}

if (!function_exists('fmtMoney')) {
    function fmtMoney(string $currencyCode, float $amount): string {
        $symbols = [
            'BDT' => '৳',
            '৳'   => '৳',
            'INR' => '₹',
            '₹'   => '₹',
            'USD' => '$',
            '$'   => '$',
        ];
        $sym = $symbols[$currencyCode] ?? $currencyCode;
        return $sym . number_format($amount, 2);
    }
}

$messId    = (int)$_SESSION['mess_id'];
$role      = $_SESSION['role'] ?? 'member';
$backupDir = __DIR__ . '/dashboard/backups';

$stmt = $pdo->prepare("SELECT * FROM messes WHERE id = ?");
$stmt->execute([$messId]);
$mess = $stmt->fetch();
$cur  = (string)($mess['currency_symbol'] ?? 'BDT');

// List backup JSON files belonging to this mess
$files = [];
if (is_dir($backupDir)) {
    $pattern = $backupDir . "/backup_mess_{$messId}_*.json";
    $matched = glob($pattern) ?: [];
    rsort($matched);
    foreach ($matched as $path) {
        $files[] = basename($path);
    }
}

$selectedFile = basename((string)($_GET['file'] ?? ($files[0] ?? '')));
$snapshot = null;
$errorMsg = '';

if ($selectedFile !== '') {
    $fullPath = $backupDir . '/' . $selectedFile;
    if (!is_file($fullPath) || !preg_match('/^backup_mess_' . $messId . '_[A-Za-z0-9_-]+\.json$/', $selectedFile)) {
        $errorMsg = "Backup file not found or invalid.";
    } else {
        $raw = file_get_contents($fullPath);
        $decoded = json_decode((string)$raw, true);
        if (!is_array($decoded) || (int)($decoded['mess']['id'] ?? 0) !== $messId) {
            $errorMsg = "Could not parse JSON snapshot or mess ID mismatch.";
        } else {
            $snapshot = $decoded;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>JSON Backup Viewer — <?= e($mess['mess_name']) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light py-4">
<div class="container-xl">
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-4">
        <div>
            <h4 class="fw-bold mb-0">Daily JSON Backup Viewer</h4>
            <div class="text-muted small"><?= e($mess['mess_name']) ?> (<?= e($mess['mess_code']) ?>)</div>
        </div>
        <div class="d-flex gap-2">
            <?php if ($role === 'manager'): ?>
                <a href="import_backup.php<?= $selectedFile !== '' ? '?file=' . urlencode($selectedFile) : '' ?>" class="btn btn-warning btn-sm fw-semibold">
                    Import / Restore Snapshot
                </a>
            <?php endif; ?>
            <a href="dashboard.php" class="btn btn-dark btn-sm fw-semibold">Back to Dashboard</a>
        </div>
    </div>

    <?php if ($errorMsg !== ''): ?>
        <div class="alert alert-danger"><?= e($errorMsg) ?></div>
    <?php endif; ?>

    <div class="card border-0 shadow-sm rounded-4 p-4 mb-4 bg-white">
        <form method="GET" class="row g-2 align-items-end">
            <div class="col-md-8">
                <label class="form-label small fw-semibold">Select Saved JSON Snapshot</label>
                <select name="file" class="form-select" onchange="this.form.submit()">
                    <?php if (empty($files)): ?>
                        <option value="">No JSON snapshots generated yet</option>
                    <?php else: foreach ($files as $f): ?>
                        <option value="<?= e($f) ?>" <?= $f === $selectedFile ? 'selected' : '' ?>><?= e($f) ?></option>
                    <?php endforeach; endif; ?>
                </select>
            </div>
            <div class="col-md-4">
                <button type="submit" class="btn btn-success w-100 fw-semibold">Load Snapshot</button>
            </div>
        </form>
    </div>

    <?php if ($snapshot):
        $sum = $snapshot['summary'] ?? [];
        $snapMembers = $snapshot['members'] ?? [];
        $perMem = $sum['members'] ?? [];
    ?>
        <div class="card border-0 shadow-sm rounded-4 p-4 mb-4 bg-white">
            <div class="d-flex justify-content-between flex-wrap gap-2 mb-3">
                <div>
                    <h5 class="fw-bold mb-0">Snapshot Month: <?= e((string)($snapshot['month'] ?? '')) ?></h5>
                    <small class="text-muted">Generated at: <?= e((string)($snapshot['generated_at'] ?? '')) ?></small>
                </div>
                <span class="badge bg-secondary-subtle text-dark border">Read-Only View</span>
            </div>

            <div class="row g-2 mb-4 small">
                <div class="col-6 col-md-2"><div class="p-2 bg-light rounded border"><strong>Total Funds:</strong><br><?= e(fmtMoney($cur, (float)($sum['total_funds'] ?? 0))) ?></div></div>
                <div class="col-6 col-md-2"><div class="p-2 bg-light rounded border"><strong>Grocery:</strong><br><?= e(fmtMoney($cur, (float)($sum['total_grocery'] ?? 0))) ?></div></div>
                <div class="col-6 col-md-2"><div class="p-2 bg-light rounded border"><strong>Misc:</strong><br><?= e(fmtMoney($cur, (float)($sum['total_misc'] ?? 0))) ?></div></div>
                <div class="col-6 col-md-2"><div class="p-2 bg-light rounded border"><strong>Cash Held:</strong><br><?= e(fmtMoney($cur, (float)($sum['cash_held'] ?? 0))) ?></div></div>
                <div class="col-6 col-md-2"><div class="p-2 bg-light rounded border"><strong>Total Meals:</strong><br><?= number_format((float)($sum['total_meals'] ?? 0), 1) ?></div></div>
                <div class="col-6 col-md-2"><div class="p-2 bg-light rounded border"><strong>Meal Rate:</strong><br><?= e(fmtMoney($cur, (float)($sum['cost_per_meal'] ?? 0))) ?></div></div>
            </div>

            <h6 class="fw-bold">Member Ledger in Snapshot</h6>
            <div class="table-responsive mb-4">
                <table class="table table-bordered table-sm align-middle">
                    <thead class="table-light">
                        <tr>
                            <th>Code &amp; Name</th>
                            <th class="text-end">Prev</th>
                            <th class="text-end">Deposit</th>
                            <th class="text-end">Own Grocery</th>
                            <th class="text-end">Meals</th>
                            <th class="text-end">Meal Cost</th>
                            <th class="text-end">Misc</th>
                            <th class="text-end">Net Balance</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($snapMembers as $m):
                            $uid = (int)($m['id'] ?? 0);
                            $md = $perMem[$uid] ?? [];
                        ?>
                        <tr>
                            <td><strong><?= e((string)($m['member_code'] ?? '')) ?></strong> <?= e((string)($m['full_name'] ?? '')) ?></td>
                            <td class="text-end font-monospace"><?= number_format((float)($md['prev'] ?? 0), 2) ?></td>
                            <td class="text-end font-monospace"><?= number_format((float)($md['deposit'] ?? 0), 2) ?></td>
                            <td class="text-end font-monospace"><?= number_format((float)($md['grocery'] ?? 0), 2) ?></td>
                            <td class="text-end"><?= number_format((float)($md['meals'] ?? 0), 1) ?></td>
                            <td class="text-end font-monospace"><?= number_format((float)($md['meal_cost'] ?? 0), 2) ?></td>
                            <td class="text-end font-monospace"><?= number_format((float)($md['misc'] ?? 0), 2) ?></td>
                            <td class="text-end font-monospace fw-bold"><?= e(fmtMoney($cur, (float)($md['net'] ?? 0))) ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <h6 class="fw-bold">Raw JSON Payload</h6>
            <pre class="bg-dark text-light p-3 rounded-3 small mb-0" style="max-height:360px;overflow:auto;"><?= e(json_encode($snapshot, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)) ?></pre>
        </div>
    <?php endif; ?>
</div>
</body>
</html>