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

$messId       = (int)$_SESSION['mess_id'];
$currentUid   = (int)$_SESSION['user_id'];
$billingMonth = $_GET['month'] ?? date('Y-m');
$autoPdf      = ($_GET['auto_pdf'] ?? '0') === '1';
$generatedAt  = date('Y-m-d H:i:s');

// Fetch Mess Info
$stmt = $pdo->prepare("SELECT * FROM messes WHERE id = ?");
$stmt->execute([$messId]);
$mess = $stmt->fetch();
$cur  = (string)($mess['currency_symbol'] ?? 'BDT');

// Active Members
$membersStmt = $pdo->prepare("SELECT * FROM users WHERE mess_id = ? AND is_active = 1 ORDER BY member_code ASC");
$membersStmt->execute([$messId]);
$members = $membersStmt->fetchAll();
$memberCount = max(1, count($members));

// Manager Previous Balance
$managerPrevBal = 0.0;
foreach ($members as $m) {
    if (($m['role'] ?? '') === 'manager') {
        $managerPrevBal += (float)($m['previous_balance'] ?? 0.0);
    }
}

// Approved Expenses
$expStmt = $pdo->prepare("
    SELECT e.*, u.full_name, u.member_code
    FROM expenses e
    JOIN users u ON e.paid_by_user_id = u.id
    WHERE e.mess_id = ? AND e.billing_month = ?
    ORDER BY e.expense_date ASC, e.id ASC
");
$expStmt->execute([$messId, $billingMonth]);
$allExpenses = $expStmt->fetchAll();

$totalGrocery = 0.0;
$managerCashGrocery = 0.0;
$memberPocketGroceryTotal = 0.0;
$totalMisc = 0.0;
$personalGrocery = [];

foreach ($allExpenses as $ex) {
    if ($ex['status'] !== 'approved') continue;
    $amt = (float)$ex['amount'];
    $source = $ex['funding_source'] ?? 'member_pocket';
    if ($ex['category'] === 'grocery') {
        $totalGrocery += $amt;
        if ($source === 'member_pocket') {
            $memberPocketGroceryTotal += $amt;
            $payerId = (int)$ex['paid_by_user_id'];
            $personalGrocery[$payerId] = ($personalGrocery[$payerId] ?? 0.0) + $amt;
        } else {
            $managerCashGrocery += $amt;
        }
    } else {
        $totalMisc += $amt;
    }
}

// Deposits & Corrections
$depStmt = $pdo->prepare("
    SELECT d.*, u.full_name, u.member_code
    FROM deposits d
    JOIN users u ON d.user_id = u.id
    WHERE d.mess_id = ? AND d.billing_month = ?
    ORDER BY d.created_at ASC
");
$depStmt->execute([$messId, $billingMonth]);
$allDeposits = $depStmt->fetchAll();

$memberDeposits = [];
$totalDeposits = 0.0;
foreach ($allDeposits as $dp) {
    $uid = (int)$dp['user_id'];
    $amt = (float)$dp['amount'];
    $memberDeposits[$uid] = ($memberDeposits[$uid] ?? 0.0) + $amt;
    $totalDeposits += $amt;
}

// Meals Summary + Full Month Matrix
$daysInMonth = (int)date('t', strtotime($billingMonth . '-01'));
$mealStmt = $pdo->prepare("
    SELECT user_id, DAY(meal_date) AS day_num, (breakfast + lunch + dinner) AS day_total
    FROM meals
    WHERE mess_id = ? AND DATE_FORMAT(meal_date, '%Y-%m') = ?
");
$mealStmt->execute([$messId, $billingMonth]);

$memberMeals = [];
$totalMeals  = 0.0;
$mealMatrix  = [];
foreach ($mealStmt->fetchAll() as $ml) {
    $uid = (int)$ml['user_id'];
    $day = (int)$ml['day_num'];
    $val = (float)$ml['day_total'];
    $mealMatrix[$uid][$day] = $val;
    $memberMeals[$uid] = ($memberMeals[$uid] ?? 0.0) + $val;
    $totalMeals += $val;
}

$costPerMeal   = $totalMeals > 0 ? ($totalGrocery / $totalMeals) : 0.0;
$miscPerMember = $totalMisc / $memberCount;
$totalFunds    = $totalDeposits + $memberPocketGroceryTotal + $managerPrevBal;
$totalExpenses = $totalGrocery + $totalMisc;
$cashHeld      = $totalFunds - $totalExpenses;

// Shopping Roster
$rosterStmt = $pdo->prepare("
    SELECT r.*, u.full_name, u.member_code
    FROM shopping_roster r
    JOIN users u ON r.assigned_user_id = u.id
    WHERE r.mess_id = ?
    ORDER BY r.duty_date ASC
");
$rosterStmt->execute([$messId]);
$rosters = $rosterStmt->fetchAll();

$safeMessName = preg_replace('/[^a-zA-Z0-9_-]+/', '_', (string)$mess['mess_name']);
$pdfFilename  = "MessMate_Backup_{$safeMessName}_{$billingMonth}_" . date('Ymd_His');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title><?= e($pdfFilename) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            font-size: 12px;
            color: #0f172a;
            background: #f1f5f9;
        }
        .report-sheet {
            max-width: 1120px;
            margin: 20px auto;
            background: #ffffff;
            padding: 28px;
            border-radius: 12px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.06);
        }
        .kpi-box {
            border: 1px solid #cbd5e1;
            border-radius: 8px;
            padding: 10px 12px;
            background: #f8fafc;
        }
        .kpi-title {
            font-size: 10px;
            text-transform: uppercase;
            font-weight: 700;
            color: #475569;
        }
        .kpi-num {
            font-size: 15px;
            font-weight: 700;
            font-family: ui-monospace, SFMono-Regular, Menlo, monospace;
        }
        table th {
            background: #e2e8f0 !important;
            color: #1e293b !important;
            font-size: 10.5px;
            text-transform: uppercase;
        }
        table td, table th {
            padding: 5px 7px !important;
            border-color: #cbd5e1 !important;
        }
        .matrix-table td, .matrix-table th {
            padding: 2px 3px !important;
            font-size: 9.5px;
            text-align: center;
        }
        .page-break {
            page-break-before: always;
        }
        @media print {
            @page {
                size: A4 landscape;
                margin: 10mm;
            }
            body {
                background: #ffffff !important;
                -webkit-print-color-adjust: exact !important;
                print-color-adjust: exact !important;
            }
            .no-print {
                display: none !important;
            }
            .report-sheet {
                box-shadow: none !important;
                margin: 0 !important;
                padding: 0 !important;
                max-width: 100% !important;
            }
        }
    </style>
</head>
<body>

<div class="no-print bg-dark text-white py-2 px-4 d-flex justify-content-between align-items-center sticky-top">
    <div>
        <strong>Daily Summary &amp; Disaster Backup Report</strong> — <?= e($mess['mess_name']) ?> (<?= e($billingMonth) ?>)
    </div>
    <div class="d-flex gap-2">
        <button onclick="window.print()" class="btn btn-success btn-sm fw-semibold">
            Save as PDF / Print
        </button>
        <a href="dashboard.php?month=<?= urlencode($billingMonth) ?>" class="btn btn-outline-light btn-sm">
            Back to Dashboard
        </a>
    </div>
</div>

<div class="report-sheet">
    <!-- HEADER -->
    <div class="d-flex justify-content-between align-items-start border-bottom pb-3 mb-3">
        <div>
            <h4 class="fw-bold mb-1"><?= e($mess['mess_name']) ?> <span class="badge bg-light text-dark border font-monospace"><?= e($mess['mess_code']) ?></span></h4>
            <div class="text-muted">Billing Period: <strong><?= e($billingMonth) ?></strong> • Active Members: <strong><?= count($members) ?></strong></div>
        </div>
        <div class="text-end">
            <div class="fw-bold text-uppercase text-success">Daily System Backup Snapshot</div>
            <div class="text-muted">Generated: <strong><?= e($generatedAt) ?></strong></div>
        </div>
    </div>

    <!-- TOP KPI SUMMARY -->
    <div class="row g-2 mb-4">
        <div class="col-4 col-md-2">
            <div class="kpi-box">
                <div class="kpi-title">Manager Prev. Bal</div>
                <div class="kpi-num"><?= ($managerPrevBal >= 0 ? '+' : '') . e(fmtMoney($cur, $managerPrevBal)) ?></div>
            </div>
        </div>
        <div class="col-4 col-md-2">
            <div class="kpi-box">
                <div class="kpi-title">Total Funds</div>
                <div class="kpi-num text-success"><?= e(fmtMoney($cur, $totalFunds)) ?></div>
                <div style="font-size:9.5px;" class="text-muted">Dep + Own Shop</div>
            </div>
        </div>
        <div class="col-4 col-md-2">
            <div class="kpi-box">
                <div class="kpi-title">Grocery Cost</div>
                <div class="kpi-num"><?= e(fmtMoney($cur, $totalGrocery)) ?></div>
                <div style="font-size:9.5px;" class="text-muted">Cash: <?= number_format($managerCashGrocery, 0) ?> | Own: <?= number_format($memberPocketGroceryTotal, 0) ?></div>
            </div>
        </div>
        <div class="col-4 col-md-2">
            <div class="kpi-box">
                <div class="kpi-title">Misc &amp; Fixed</div>
                <div class="kpi-num"><?= e(fmtMoney($cur, $totalMisc)) ?></div>
                <div style="font-size:9.5px;" class="text-muted"><?= e(fmtMoney($cur, $miscPerMember)) ?> / head</div>
            </div>
        </div>
        <div class="col-4 col-md-2">
            <div class="kpi-box">
                <div class="kpi-title">Cash With Manager</div>
                <div class="kpi-num <?= $cashHeld >= 0 ? 'text-success' : 'text-danger' ?>"><?= e(fmtMoney($cur, $cashHeld)) ?></div>
                <div style="font-size:9.5px;" class="text-muted">Funds - (Groc + Misc)</div>
            </div>
        </div>
        <div class="col-4 col-md-2">
            <div class="kpi-box bg-dark text-white">
                <div class="kpi-title text-white-50">Live Meal Rate</div>
                <div class="kpi-num text-warning"><?= e(fmtMoney($cur, $costPerMeal)) ?></div>
                <div style="font-size:9.5px;" class="text-white-50">Meals: <?= number_format($totalMeals, 1) ?></div>
            </div>
        </div>
    </div>

    <!-- 1. MEMBER ACCOUNTS & LEDGER -->
    <h6 class="fw-bold text-uppercase mb-2">1. Member Accounts &amp; Meal Ledger (<?= e($billingMonth) ?>)</h6>
    <table class="table table-bordered align-middle mb-4">
        <thead>
            <tr>
                <th>ID &amp; Member</th>
                <th>Room / Seat</th>
                <th class="text-end">Prev. Bal</th>
                <th class="text-end">Deposited</th>
                <th class="text-end">Own Grocery</th>
                <th class="text-end">Meals</th>
                <th class="text-end">Meal Cost</th>
                <th class="text-end">Misc Share</th>
                <th class="text-end">Net Balance</th>
            </tr>
        </thead>
        <tbody>
            <?php
            $sPrev = $sDep = $sShop = $sMeals = $sMCost = $sMisc = $sNet = 0.0;
            foreach ($members as $m):
                $uid   = (int)$m['id'];
                $prev  = (float)$m['previous_balance'];
                $dep   = $memberDeposits[$uid] ?? 0.0;
                $shop  = $personalGrocery[$uid] ?? 0.0;
                $mls   = $memberMeals[$uid] ?? 0.0;
                $mCost = $mls * $costPerMeal;
                $net   = ($prev + $dep + $shop) - ($mCost + $miscPerMember);

                $sPrev += $prev; $sDep += $dep; $sShop += $shop;
                $sMeals += $mls; $sMCost += $mCost; $sMisc += $miscPerMember; $sNet += $net;
            ?>
            <tr>
                <td><strong><?= e($m['member_code']) ?></strong> — <?= e($m['full_name']) ?></td>
                <td><?= e(($m['room_no'] ?: '—') . ' / ' . ($m['seat_no'] ?: '—')) ?></td>
                <td class="text-end font-monospace"><?= ($prev >= 0 ? '+' : '') . number_format($prev, 2) ?></td>
                <td class="text-end font-monospace"><?= number_format($dep, 2) ?></td>
                <td class="text-end font-monospace"><?= number_format($shop, 2) ?></td>
                <td class="text-end fw-bold"><?= number_format($mls, 1) ?></td>
                <td class="text-end font-monospace"><?= number_format($mCost, 2) ?></td>
                <td class="text-end font-monospace"><?= number_format($miscPerMember, 2) ?></td>
                <td class="text-end font-monospace fw-bold <?= $net >= 0 ? 'text-success' : 'text-danger' ?>">
                    <?= ($net >= 0 ? '+' : '') . e(fmtMoney($cur, $net)) ?>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
        <tfoot class="fw-bold table-light">
            <tr>
                <td colspan="2">TOTAL (<?= count($members) ?> Members)</td>
                <td class="text-end font-monospace"><?= ($sPrev >= 0 ? '+' : '') . number_format($sPrev, 2) ?></td>
                <td class="text-end font-monospace"><?= number_format($sDep, 2) ?></td>
                <td class="text-end font-monospace"><?= number_format($sShop, 2) ?></td>
                <td class="text-end"><?= number_format($sMeals, 1) ?></td>
                <td class="text-end font-monospace"><?= number_format($sMCost, 2) ?></td>
                <td class="text-end font-monospace"><?= number_format($sMisc, 2) ?></td>
                <td class="text-end font-monospace"><?= ($sNet >= 0 ? '+' : '') . e(fmtMoney($cur, $sNet)) ?></td>
            </tr>
        </tfoot>
    </table>

    <!-- 2. FULL MONTH DAILY MEAL MATRIX -->
    <h6 class="fw-bold text-uppercase mb-2">2. Full Month Daily Meal Matrix (Day 1 – <?= $daysInMonth ?>)</h6>
    <table class="table table-bordered matrix-table mb-4">
        <thead>
            <tr>
                <th class="text-start">Member</th>
                <?php for ($d = 1; $d <= $daysInMonth; $d++): ?>
                    <th><?= $d ?></th>
                <?php endfor; ?>
                <th>Total</th>
            </tr>
        </thead>
        <tbody>
            <?php
            $colTotals = array_fill(1, $daysInMonth, 0.0);
            foreach ($members as $m):
                $uid = (int)$m['id'];
                $rowSum = 0.0;
            ?>
            <tr>
                <td class="text-start text-nowrap"><strong><?= e($m['member_code']) ?></strong> <?= e($m['full_name']) ?></td>
                <?php for ($d = 1; $d <= $daysInMonth; $d++):
                    $val = $mealMatrix[$uid][$d] ?? 0.0;
                    $rowSum += $val;
                    $colTotals[$d] += $val;
                ?>
                    <td><?= $val > 0 ? number_format($val, 1) : '·' ?></td>
                <?php endfor; ?>
                <td class="fw-bold"><?= number_format($rowSum, 1) ?></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
        <tfoot class="fw-bold table-light">
            <tr>
                <td class="text-start">Daily Total</td>
                <?php for ($d = 1; $d <= $daysInMonth; $d++): ?>
                    <td><?= number_format($colTotals[$d], 1) ?></td>
                <?php endfor; ?>
                <td><?= number_format(array_sum($colTotals), 1) ?></td>
            </tr>
        </tfoot>
    </table>

    <div class="page-break"></div>

    <!-- 3. DEPOSIT & CORRECTION LOGS + EXPENSE LOGS -->
    <div class="row g-3 mb-4">
        <div class="col-md-6">
            <h6 class="fw-bold text-uppercase mb-2">3. Deposit &amp; Correction History</h6>
            <table class="table table-bordered align-middle mb-0">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Member</th>
                        <th>Type</th>
                        <th>Note</th>
                        <th class="text-end">Amount</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($allDeposits)): ?>
                        <tr><td colspan="5" class="text-center text-muted">No deposits recorded this month.</td></tr>
                    <?php else: foreach ($allDeposits as $dp): ?>
                        <tr>
                            <td class="text-nowrap"><?= date('Y-m-d', strtotime($dp['created_at'])) ?></td>
                            <td><?= e($dp['member_code']) ?> <?= e($dp['full_name']) ?></td>
                            <td><?= ucfirst(e($dp['entry_type'])) ?></td>
                            <td><?= e($dp['note']) ?></td>
                            <td class="text-end font-monospace fw-bold"><?= e(fmtMoney($cur, (float)$dp['amount'])) ?></td>
                        </tr>
                    <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>

        <div class="col-md-6">
            <h6 class="fw-bold text-uppercase mb-2">4. Grocery &amp; Misc Expense Log</h6>
            <table class="table table-bordered align-middle mb-0">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Cat.</th>
                        <th>Description</th>
                        <th>Paid By / Source</th>
                        <th>Status</th>
                        <th class="text-end">Amount</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($allExpenses)): ?>
                        <tr><td colspan="6" class="text-center text-muted">No expenses recorded this month.</td></tr>
                    <?php else: foreach ($allExpenses as $ex):
                        $src = ($ex['funding_source'] ?? 'member_pocket') === 'manager_cash'
                            ? 'Manager Cash'
                            : $ex['member_code'] . ' (' . $ex['full_name'] . ')';
                    ?>
                        <tr>
                            <td class="text-nowrap"><?= e($ex['expense_date']) ?></td>
                            <td><?= strtoupper(e($ex['category'])) ?></td>
                            <td><?= e($ex['description']) ?></td>
                            <td><?= e($src) ?></td>
                            <td><?= ucfirst(e($ex['status'])) ?></td>
                            <td class="text-end font-monospace fw-bold"><?= e(fmtMoney($cur, (float)$ex['amount'])) ?></td>
                        </tr>
                    <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- 5. SHOPPING ROSTER -->
    <h6 class="fw-bold text-uppercase mb-2">5. Shopping Duty Roster</h6>
    <table class="table table-bordered align-middle mb-0">
        <thead>
            <tr>
                <th>Date / Range</th>
                <th>Assigned Member</th>
                <th>Notes</th>
                <th>Status</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($rosters)): ?>
                <tr><td colspan="4" class="text-center text-muted">No shopping roster duties scheduled.</td></tr>
            <?php else: foreach ($rosters as $r):
                $range = (!empty($r['duty_end_date']) && $r['duty_end_date'] !== $r['duty_date'])
                    ? $r['duty_date'] . ' → ' . $r['duty_end_date']
                    : $r['duty_date'];
            ?>
                <tr>
                    <td><?= e($range) ?></td>
                    <td><?= e($r['member_code']) ?> — <?= e($r['full_name']) ?></td>
                    <td><?= e($r['items_note'] ?: 'Regular grocery bazar') ?></td>
                    <td><?= ucfirst(e($r['status'])) ?></td>
                </tr>
            <?php endforeach; endif; ?>
        </tbody>
    </table>
</div>

<?php if ($autoPdf): ?>
<script>
window.addEventListener('load', function () {
    setTimeout(function () {
        window.print();
    }, 250);
});
</script>
<?php endif; ?>
</body>
</html>