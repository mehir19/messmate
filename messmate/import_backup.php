<?php
require 'db.php';

if (!isset($_SESSION['user_id'], $_SESSION['mess_id'])) {
    header("Location: login.php");
    exit;
}

$messId     = (int)$_SESSION['mess_id'];
$currentUid = (int)$_SESSION['user_id'];
$role       = $_SESSION['role'] ?? 'member';
$backupDir  = __DIR__ . '/dashboard/backups';
$flash      = '';
$flashError = '';

if ($role !== 'manager') {
    $flashError = "Only the Mess Manager can import or restore backup JSON files.";
}

$stmt = $pdo->prepare("SELECT * FROM messes WHERE id = ?");
$stmt->execute([$messId]);
$mess = $stmt->fetch();

$files = [];
if (is_dir($backupDir)) {
    $matched = glob($backupDir . "/backup_mess_{$messId}_*.json") ?: [];
    rsort($matched);
    foreach ($matched as $path) {
        $files[] = basename($path);
    }
}

$selectedFile = basename((string)($_GET['file'] ?? ($files[0] ?? '')));

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $role === 'manager') {
    $rawJson = '';

    if (!empty($_FILES['json_file']['tmp_name']) && is_uploaded_file($_FILES['json_file']['tmp_name'])) {
        $rawJson = (string)file_get_contents($_FILES['json_file']['tmp_name']);
    } else {
        $chosen = basename((string)($_POST['saved_file'] ?? ''));
        $fullPath = $backupDir . '/' . $chosen;
        if ($chosen !== '' && is_file($fullPath) && preg_match('/^backup_mess_' . $messId . '_[A-Za-z0-9_-]+\.json$/', $chosen)) {
            $rawJson = (string)file_get_contents($fullPath);
        }
    }

    $data = json_decode($rawJson, true);
    if (!is_array($data) || empty($data['month']) || !preg_match('/^\d{4}-\d{2}$/', (string)$data['month'])) {
        $flashError = "Invalid backup JSON: missing valid billing month (YYYY-MM).";
    } elseif (isset($data['mess']['id']) && (int)$data['mess']['id'] !== $messId) {
        $flashError = "Backup JSON belongs to a different Mess ID.";
    } else {
        $month = (string)$data['month'];
        $monthStart = $month . '-01';
        $monthEnd   = date('Y-m-t', strtotime($monthStart));

        // Map existing active users by member_code and old id
        $uStmt = $pdo->prepare("SELECT id, member_code FROM users WHERE mess_id = ?");
        $uStmt->execute([$messId]);
        $codeToUid = [];
        $oldIdToUid = [];
        foreach ($uStmt->fetchAll() as $uRow) {
            $codeToUid[(string)$uRow['member_code']] = (int)$uRow['id'];
            $oldIdToUid[(int)$uRow['id']] = (int)$uRow['id'];
        }

        // Also map snapshot members' old id -> current uid via member_code
        foreach (($data['members'] ?? []) as $sm) {
            $code = (string)($sm['member_code'] ?? '');
            $oldId = (int)($sm['id'] ?? 0);
            if ($code !== '' && isset($codeToUid[$code]) && $oldId > 0) {
                $oldIdToUid[$oldId] = $codeToUid[$code];
            }
        }

        try {
            $pdo->beginTransaction();

            // 1. Restore previous_balance for matched members if present
            if (!empty($data['members']) && is_array($data['members'])) {
                $updPrev = $pdo->prepare("UPDATE users SET previous_balance = ? WHERE id = ? AND mess_id = ?");
                foreach ($data['members'] as $sm) {
                    $code = (string)($sm['member_code'] ?? '');
                    $targetUid = $codeToUid[$code] ?? ($oldIdToUid[(int)($sm['id'] ?? 0)] ?? 0);
                    if ($targetUid > 0 && isset($sm['previous_balance'])) {
                        $updPrev->execute([(float)$sm['previous_balance'], $targetUid, $messId]);
                    }
                }
            }

            // 2. Replace deposits for this mess & billing_month
            if (isset($data['deposits']) && is_array($data['deposits'])) {
                $delDep = $pdo->prepare("DELETE FROM deposits WHERE mess_id = ? AND billing_month = ?");
                $delDep->execute([$messId, $month]);

                $insDep = $pdo->prepare("
                    INSERT INTO deposits (mess_id, user_id, amount, entry_type, note, recorded_by, billing_month)
                    VALUES (?, ?, ?, ?, ?, ?, ?)
                ");
                foreach ($data['deposits'] as $dp) {
                    $uid = $codeToUid[(string)($dp['member_code'] ?? '')] ?? ($oldIdToUid[(int)($dp['user_id'] ?? 0)] ?? 0);
                    if ($uid > 0) {
                        $insDep->execute([
                            $messId,
                            $uid,
                            (float)($dp['amount'] ?? 0),
                            in_array($dp['entry_type'] ?? '', ['deposit', 'correction'], true) ? $dp['entry_type'] : 'deposit',
                            (string)($dp['note'] ?? 'Restored from JSON backup'),
                            $currentUid,
                            $month,
                        ]);
                    }
                }
            }

            // 3. Replace expenses for this mess & billing_month
            if (isset($data['expenses']) && is_array($data['expenses'])) {
                $delExp = $pdo->prepare("DELETE FROM expenses WHERE mess_id = ? AND billing_month = ?");
                $delExp->execute([$messId, $month]);

                $insExp = $pdo->prepare("
                    INSERT INTO expenses (mess_id, paid_by_user_id, funding_source, category, description, amount, expense_date, status, billing_month)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
                ");
                foreach ($data['expenses'] as $ex) {
                    $uid = $codeToUid[(string)($ex['member_code'] ?? '')] ?? ($oldIdToUid[(int)($ex['paid_by_user_id'] ?? 0)] ?? $currentUid);
                    $insExp->execute([
                        $messId,
                        $uid,
                        in_array($ex['funding_source'] ?? '', ['manager_cash', 'member_pocket'], true) ? $ex['funding_source'] : 'member_pocket',
                        ($ex['category'] ?? 'grocery') === 'misc' ? 'misc' : 'grocery',
                        (string)($ex['description'] ?? 'Restored expense'),
                        (float)($ex['amount'] ?? 0),
                        !empty($ex['expense_date']) ? (string)$ex['expense_date'] : $monthStart,
                        in_array($ex['status'] ?? '', ['approved', 'pending', 'rejected'], true) ? $ex['status'] : 'approved',
                        $month,
                    ]);
                }
            }

            // 4. Replace meals for this mess & month when meal_matrix is present
            if (isset($data['meal_matrix']) && is_array($data['meal_matrix'])) {
                $delMeals = $pdo->prepare("DELETE FROM meals WHERE mess_id = ? AND meal_date BETWEEN ? AND ?");
                $delMeals->execute([$messId, $monthStart, $monthEnd]);

                $insMeal = $pdo->prepare("
                    INSERT INTO meals (mess_id, user_id, meal_date, breakfast, lunch, dinner)
                    VALUES (?, ?, ?, 0, ?, 0)
                    ON DUPLICATE KEY UPDATE lunch = VALUES(lunch)
                ");
                foreach ($data['meal_matrix'] as $oldUidKey => $days) {
                    $uid = $oldIdToUid[(int)$oldUidKey] ?? 0;
                    if ($uid <= 0 || !is_array($days)) continue;
                    foreach ($days as $dayNum => $dayTotal) {
                        $dayInt = (int)$dayNum;
                        $tot = (float)$dayTotal;
                        if ($dayInt >= 1 && $dayInt <= 31 && $tot >= 0) {
                            $dateStr = sprintf('%s-%02d', $month, $dayInt);
                            if (strtotime($dateStr) !== false) {
                                $insMeal->execute([$messId, $uid, $dateStr, $tot]);
                            }
                        }
                    }
                }
            }

            $pdo->commit();
            $flash = "Successfully imported JSON backup for {$month}.";
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $flashError = "Import failed and was rolled back: " . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Import JSON Backup — <?= e($mess['mess_name']) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light py-4">
<div class="container-xl" style="max-width: 780px;">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h4 class="fw-bold mb-0">Import / Restore JSON Backup</h4>
            <div class="text-muted small"><?= e($mess['mess_name']) ?> (<?= e($mess['mess_code']) ?>)</div>
        </div>
        <div class="d-flex gap-2">
            <a href="view_backup.php" class="btn btn-outline-secondary btn-sm fw-semibold">View Snapshots</a>
            <a href="dashboard.php" class="btn btn-dark btn-sm fw-semibold">Back to Dashboard</a>
        </div>
    </div>

    <?php if ($flash !== ''): ?>
        <div class="alert alert-success fw-semibold"><?= e($flash) ?></div>
    <?php endif; ?>
    <?php if ($flashError !== ''): ?>
        <div class="alert alert-danger fw-semibold"><?= e($flashError) ?></div>
    <?php endif; ?>

    <?php if ($role === 'manager'): ?>
    <div class="card border-0 shadow-sm rounded-4 p-4 bg-white">
        <div class="alert alert-warning small">
            <strong>Caution:</strong> Importing a JSON snapshot replaces that snapshot's <strong>billing month</strong> deposits, expenses, and meals in the database using a safe transaction.
        </div>

        <form method="POST" enctype="multipart/form-data">
            <div class="mb-3">
                <label class="form-label small fw-semibold">Option 1: Choose a Saved Server Snapshot</label>
                <select name="saved_file" class="form-select">
                    <option value="">-- Select saved JSON file --</option>
                    <?php foreach ($files as $f): ?>
                        <option value="<?= e($f) ?>" <?= $f === $selectedFile ? 'selected' : '' ?>><?= e($f) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="mb-4">
                <label class="form-label small fw-semibold">Option 2: Upload a .json Backup File</label>
                <input type="file" name="json_file" accept=".json,application/json" class="form-control">
            </div>

            <button type="submit" class="btn btn-danger fw-semibold" onclick="return confirm('Restore data from this JSON backup? Existing entries for that billing month will be replaced.');">
                Import &amp; Restore Backup
            </button>
        </form>
    </div>
    <?php endif; ?>
</div>
</body>
</html>