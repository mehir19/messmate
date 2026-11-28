<?php
require 'db.php';
date_default_timezone_set('Asia/Dhaka');

if (isset($_GET['logout'])) {
    unset($_SESSION['cook_mess_id']);
    header("Location: login.php");
    exit;
}

if (empty($_SESSION['cook_mess_id'])) {
    header("Location: login.php");
    exit;
}

$messId = (int)$_SESSION['cook_mess_id'];
$selectedDate = $_GET['date'] ?? date('Y-m-d');
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $selectedDate)) {
    $selectedDate = date('Y-m-d');
}

// BD (GMT+6) cooking slot windows:
// Before 10:00 AM -> breakfast
// 10:00 AM to before 3:00 PM (10..14) -> lunch
// 3:00 PM (15:00) and after -> dinner
$bdHour = (int)date('G');
if ($bdHour < 10) {
    $activeSlot = 'breakfast';
} elseif ($bdHour < 15) {
    $activeSlot = 'lunch';
} else {
    $activeSlot = 'dinner';
}

$messStmt = $pdo->prepare("SELECT * FROM messes WHERE id = ?");
$messStmt->execute([$messId]);
$mess = $messStmt->fetch();
if (!$mess) {
    unset($_SESSION['cook_mess_id']);
    header("Location: login.php");
    exit;
}

$rowsStmt = $pdo->prepare("
    SELECT u.full_name, u.member_code, u.room_no, u.seat_no,
           COALESCE(m.breakfast, 0) AS breakfast,
           COALESCE(m.lunch, 0) AS lunch,
           COALESCE(m.dinner, 0) AS dinner
    FROM users u
    LEFT JOIN meals m ON m.user_id = u.id AND m.mess_id = u.mess_id AND m.meal_date = ?
    WHERE u.mess_id = ? AND u.is_active = 1
    ORDER BY u.member_code ASC
");
$rowsStmt->execute([$selectedDate, $messId]);
$mealRows = $rowsStmt->fetchAll();

$totals = ['breakfast' => 0.0, 'lunch' => 0.0, 'dinner' => 0.0];
foreach ($mealRows as $r) {
    $totals['breakfast'] += (float)$r['breakfast'];
    $totals['lunch']     += (float)$r['lunch'];
    $totals['dinner']    += (float)$r['dinner'];
}
$grandTotal = $totals['breakfast'] + $totals['lunch'] + $totals['dinner'];

// Fetch recent messages sent by the manager (for cooking instructions)
$instStmt = $pdo->prepare("
    SELECT c.message, c.created_at, u.full_name
    FROM chat_messages c
    JOIN users u ON c.sender_id = u.id
    WHERE c.mess_id = ?
      AND u.role = 'manager'
      AND c.request_type = 'chat'
    ORDER BY c.created_at DESC
    LIMIT 5
");
$instStmt->execute([$messId]);
$managerInstructions = $instStmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cook Meal View — <?= e($mess['mess_name']) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light py-4">
<div class="container" style="max-width: 820px;">
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-4">
        <div>
            <h4 class="fw-bold mb-0"><?= e($mess['mess_name']) ?> <span class="badge bg-dark font-monospace"><?= e($mess['mess_code']) ?></span></h4>
            <div class="text-muted small">
                BD Time (GMT+6): <strong><?= date('d-m-y h:i A') ?></strong> • Current Slot: <strong class="text-uppercase"><?= e($activeSlot) ?></strong>
            </div>
        </div>
        <div class="d-flex gap-2">
            <form method="GET" class="d-flex gap-2 mb-0">
                <input type="date" name="date" value="<?= e($selectedDate) ?>" onchange="this.form.submit()" class="form-control form-control-sm btn btn-outline-dark btn-sm">
            </form>
            <a href="cooking.php?logout=1" class="btn btn-outline-danger btn-sm fw-semibold">Logout</a>
        </div>
    </div>

    <!-- Manager Cooking Instructions Box -->
    <div class="card border-0 border-start border-4 border-warning shadow-sm rounded-4 p-4 mb-4 bg-white">
        <div class="d-flex justify-content-between align-items-center mb-2">
            <h6 class="fw-bold mb-0">Manager Cooking Instructions &amp; Notes</h6>
            <span class="badge bg-warning-subtle text-dark">From Manager</span>
        </div>
        <?php if (empty($managerInstructions)): ?>
            <div class="text-muted small mb-0">No cooking instructions posted by the manager yet.</div>
        <?php else: ?>
            <div class="d-flex flex-column gap-2">
                <?php foreach ($managerInstructions as $inst): ?>
                    <div class="p-3 rounded-3 bg-light border small">
                        <div class="fw-semibold text-dark"><?= e($inst['message']) ?></div>
                        <div class="text-muted mt-1" style="font-size: 0.75rem;">
                            <?= e($inst['full_name']) ?> • <?= fmtDateDmy($inst['created_at'], true) ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <!-- Slot-Wise Totals -->
    <div class="row g-3 mb-4 text-center">
        <div class="col-4">
            <div class="p-3 rounded-4 border <?= $activeSlot === 'breakfast' ? 'border-3 border-warning shadow' : '' ?>" style="background:#fef3c7;color:#92400e;">
                <div class="small fw-bold text-uppercase">Breakfast <?= $activeSlot === 'breakfast' ? '• Current' : '' ?></div>
                <div class="display-6 fw-bold"><?= number_format($totals['breakfast'], 1) ?></div>
            </div>
        </div>
        <div class="col-4">
            <div class="p-3 rounded-4 border <?= $activeSlot === 'lunch' ? 'border-3 border-info shadow' : '' ?>" style="background:#e0f2fe;color:#075985;">
                <div class="small fw-bold text-uppercase">Lunch <?= $activeSlot === 'lunch' ? '• Current' : '' ?></div>
                <div class="display-6 fw-bold"><?= number_format($totals['lunch'], 1) ?></div>
            </div>
        </div>
        <div class="col-4">
            <div class="p-3 rounded-4 border <?= $activeSlot === 'dinner' ? 'border-3 border-primary shadow' : '' ?>" style="background:#ede9fe;color:#5b21b6;">
                <div class="small fw-bold text-uppercase">Dinner <?= $activeSlot === 'dinner' ? '• Current' : '' ?></div>
                <div class="display-6 fw-bold"><?= number_format($totals['dinner'], 1) ?></div>
            </div>
        </div>
    </div>

    <!-- Member Slot Breakdown -->
    <div class="card border-0 shadow-sm rounded-4 p-4 bg-white">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h6 class="fw-bold mb-0">Member Slot Breakdown (<?= fmtDateDmy($selectedDate) ?>)</h6>
            <span class="badge bg-dark">Total Meals: <?= number_format($grandTotal, 1) ?></span>
        </div>
        <div class="table-responsive">
            <table class="table table-bordered align-middle mb-0">
                <thead class="table-light small text-uppercase">
                    <tr>
                        <th>Member</th>
                        <th class="text-center" style="background:#fef3c7;color:#92400e;">Breakfast</th>
                        <th class="text-center" style="background:#e0f2fe;color:#075985;">Lunch</th>
                        <th class="text-center" style="background:#ede9fe;color:#5b21b6;">Dinner</th>
                        <th class="text-end">Total</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($mealRows as $r):
                        $b = (float)$r['breakfast'];
                        $l = (float)$r['lunch'];
                        $d = (float)$r['dinner'];
                    ?>
                    <tr>
                        <td class="fw-semibold"><?= e($r['full_name']) ?> <span class="text-muted small">(<?= e($r['member_code']) ?>)</span></td>
                        <td class="text-center fw-bold" style="background:#fffbeb;"><?= number_format($b, 1) ?></td>
                        <td class="text-center fw-bold" style="background:#f0f9ff;"><?= number_format($l, 1) ?></td>
                        <td class="text-center fw-bold" style="background:#f5f3ff;"><?= number_format($d, 1) ?></td>
                        <td class="text-end fw-bold"><?= number_format($b + $l + $d, 1) ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
                <tfoot class="fw-bold">
                    <tr>
                        <td>Total</td>
                        <td class="text-center" style="background:#fde68a;color:#78350f;"><?= number_format($totals['breakfast'], 1) ?></td>
                        <td class="text-center" style="background:#bae6fd;color:#0c4a6e;"><?= number_format($totals['lunch'], 1) ?></td>
                        <td class="text-center" style="background:#ddd6fe;color:#4c1d95;"><?= number_format($totals['dinner'], 1) ?></td>
                        <td class="text-end table-dark text-white"><?= number_format($grandTotal, 1) ?></td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>
</div>
</body>
</html>