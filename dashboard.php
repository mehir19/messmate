<?php
require 'db.php';

if (!isset($_SESSION['user_id'], $_SESSION['mess_id'])) {
    header("Location: login.php");
    exit;
}

$messId       = (int)$_SESSION['mess_id'];
$currentUid   = (int)$_SESSION['user_id'];
$role         = $_SESSION['role'];
$billingMonth = $_GET['month'] ?? date('Y-m');
$prevMonth    = date('Y-m', strtotime($billingMonth . '-01 -1 month'));
$today        = date('Y-m-d');
$activeTab    = $_GET['tab'] ?? 'overview';
$flash        = '';

// Fetch Mess & Subscription
$stmt = $pdo->prepare("SELECT * FROM messes WHERE id = ?");
$stmt->execute([$messId]);
$mess = $stmt->fetch();
$subInfo = checkSubscriptionActive($mess);
$cur = $mess['currency_symbol'];

// Helper to compute full monthly financial summary for any given YYYY-MM
function getMonthSummary(PDO $pdo, int $messId, string $month, array $members): array {
    $memberCount = max(1, count($members));

    // Approved Expenses
    $expStmt = $pdo->prepare("SELECT * FROM expenses WHERE mess_id = ? AND billing_month = ? AND status = 'approved'");
    $expStmt->execute([$messId, $month]);
    $totalGrocery = 0.0;
    $totalMisc = 0.0;
    $personalGrocery = [];
    foreach ($expStmt->fetchAll() as $ex) {
        if ($ex['category'] === 'grocery') {
            $totalGrocery += (float)$ex['amount'];
            $personalGrocery[$ex['paid_by_user_id']] = ($personalGrocery[$ex['paid_by_user_id']] ?? 0) + (float)$ex['amount'];
        } else {
            $totalMisc += (float)$ex['amount'];
        }
    }

    // Deposits & Corrections
    $depStmt = $pdo->prepare("SELECT user_id, SUM(amount) AS total_dep FROM deposits WHERE mess_id = ? AND billing_month = ? GROUP BY user_id");
    $depStmt->execute([$messId, $month]);
    $memberDeposits = [];
    $totalDeposits = 0.0;
    foreach ($depStmt->fetchAll() as $dp) {
        $memberDeposits[$dp['user_id']] = (float)$dp['total_dep'];
        $totalDeposits += (float)$dp['total_dep'];
    }

    // Meals
    $mealStmt = $pdo->prepare("SELECT user_id, SUM(breakfast + lunch + dinner) AS total_meals FROM meals WHERE mess_id = ? AND DATE_FORMAT(meal_date, '%Y-%m') = ? GROUP BY user_id");
    $mealStmt->execute([$messId, $month]);
    $memberMeals = [];
    $totalMeals = 0.0;
    foreach ($mealStmt->fetchAll() as $ml) {
        $memberMeals[$ml['user_id']] = (float)$ml['total_meals'];
        $totalMeals += (float)$ml['total_meals'];
    }

    $costPerMeal = $totalMeals > 0 ? ($totalGrocery / $totalMeals) : 0.0;
    $miscPerMember = $totalMisc / $memberCount;

    $perMember = [];
    foreach ($members as $m) {
        $uid = (int)$m['id'];
        $prev = (float)$m['previous_balance'];
        $dep  = $memberDeposits[$uid] ?? 0.0;
        $shop = $personalGrocery[$uid] ?? 0.0;
        $mls  = $memberMeals[$uid] ?? 0.0;
        $mCost = $mls * $costPerMeal;
        $net  = ($prev + $dep + $shop) - ($mCost + $miscPerMember);

        $perMember[$uid] = [
            'prev'      => $prev,
            'deposit'   => $dep,
            'grocery'   => $shop,
            'meals'     => $mls,
            'meal_cost' => $mCost,
            'misc'      => $miscPerMember,
            'net'       => $net,
        ];
    }

    return [
        'total_deposits' => $totalDeposits,
        'total_grocery'  => $totalGrocery,
        'total_misc'     => $totalMisc,
        'total_meals'    => $totalMeals,
        'cost_per_meal'  => $costPerMeal,
        'misc_per_member'=> $miscPerMember,
        'cash_held'      => $totalDeposits - $totalGrocery,
        'members'        => $perMember,
    ];
}

// Handle POST Actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action    = $_POST['action'] ?? '';
    $activeTab = $_POST['current_tab'] ?? 'overview';

    // --- MEMBER CRUD ---
    if ($action === 'add_member' && $role === 'manager' && $subInfo['active']) {
        $name    = trim($_POST['full_name']);
        $prevBal = (float)$_POST['previous_balance'];
        $pin     = $_POST['pin'] ?: '123456';

        $countStmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE mess_id = ?");
        $countStmt->execute([$messId]);
        $nextCode = 'M-' . (101 + (int)$countStmt->fetchColumn());

        $ins = $pdo->prepare("INSERT INTO users (mess_id, member_code, full_name, password_hash, role, previous_balance) VALUES (?, ?, ?, ?, 'member', ?)");
        $ins->execute([$messId, $nextCode, $name, password_hash($pin, PASSWORD_BCRYPT), $prevBal]);
        $flash = "Added {$name} ({$nextCode}) with PIN: {$pin}";
    }

    if ($action === 'edit_member' && $role === 'manager' && $subInfo['active']) {
        $targetUid = (int)$_POST['user_id'];
        $name      = trim($_POST['full_name']);
        $prevBal   = (float)$_POST['previous_balance'];
        $newPin    = trim($_POST['new_pin'] ?? '');

        if ($newPin !== '') {
            $upd = $pdo->prepare("UPDATE users SET full_name = ?, previous_balance = ?, password_hash = ? WHERE id = ? AND mess_id = ?");
            $upd->execute([$name, $prevBal, password_hash($newPin, PASSWORD_BCRYPT), $targetUid, $messId]);
        } else {
            $upd = $pdo->prepare("UPDATE users SET full_name = ?, previous_balance = ? WHERE id = ? AND mess_id = ?");
            $upd->execute([$name, $prevBal, $targetUid, $messId]);
        }
        $flash = "Member details updated.";
    }

    if ($action === 'delete_member' && $role === 'manager' && $subInfo['active']) {
        $targetUid = (int)$_POST['user_id'];
        if ($targetUid === $currentUid) {
            $flashError = "You cannot delete your own manager account.";
        } else {
            // Check deposits in current billing month
            $chkDep = $pdo->prepare("SELECT COUNT(*) FROM deposits WHERE mess_id = ? AND user_id = ? AND billing_month = ?");
            $chkDep->execute([$messId, $targetUid, $billingMonth]);
            $depCount = (int)$chkDep->fetchColumn();

            // Check meals in current billing month
            $chkMeal = $pdo->prepare("SELECT COUNT(*) FROM meals WHERE mess_id = ? AND user_id = ? AND DATE_FORMAT(meal_date, '%Y-%m') = ?");
            $chkMeal->execute([$messId, $targetUid, $billingMonth]);
            $mealCount = (int)$chkMeal->fetchColumn();

            // Check expenses paid by this member in current billing month
            $chkExp = $pdo->prepare("SELECT COUNT(*) FROM expenses WHERE mess_id = ? AND paid_by_user_id = ? AND billing_month = ?");
            $chkExp->execute([$messId, $targetUid, $billingMonth]);
            $expCount = (int)$chkExp->fetchColumn();

            if ($depCount > 0 || $mealCount > 0 || $expCount > 0) {
                $reasons = [];
                if ($depCount > 0)  $reasons[] = "{$depCount} deposit(s)";
                if ($mealCount > 0) $reasons[] = "{$mealCount} meal day(s)";
                if ($expCount > 0)  $reasons[] = "{$expCount} expense(s)";

                $flashError = "Cannot delete this member for {$billingMonth}: they already have " . implode(', ', $reasons) . " in this month.";
            } else {
                $del = $pdo->prepare("UPDATE users SET is_active = 0 WHERE id = ? AND mess_id = ? AND role = 'member'");
                $del->execute([$targetUid, $messId]);
                $flash = "Member removed from active mess roster.";
            }
        }
    }

    // --- DEPOSIT & ONE-CLICK CORRECTION ---
    if ($action === 'add_deposit' && $role === 'manager' && $subInfo['active']) {
        $targetUid = (int)$_POST['user_id'];
        $amount    = (float)$_POST['amount'];
        $note      = trim($_POST['note']) ?: 'Monthly fund contribution';
        if ($amount > 0) {
            $ins = $pdo->prepare("INSERT INTO deposits (mess_id, user_id, amount, entry_type, note, recorded_by, billing_month) VALUES (?, ?, ?, 'deposit', ?, ?, ?)");
            $ins->execute([$messId, $targetUid, $amount, $note, $currentUid, $billingMonth]);
            $flash = "Deposit of {$cur}" . number_format($amount, 2) . " recorded.";
        }
    }

    if ($action === 'correct_deposit' && $role === 'manager' && $subInfo['active']) {
        $depId         = (int)$_POST['deposit_id'];
        $correctAmount = (float)$_POST['correct_amount'];
        $reason        = trim($_POST['reason']) ?: 'Amount entry fix';

        $origStmt = $pdo->prepare("SELECT * FROM deposits WHERE id = ? AND mess_id = ?");
        $origStmt->execute([$depId, $messId]);
        $orig = $origStmt->fetch();

        if ($orig) {
            $wrongAmount = (float)$orig['amount'];
            $diff = $correctAmount - $wrongAmount;
            if ($diff != 0) {
                $auditNote = sprintf("Fixed Deposit #%d (Was %s%.2f → Now %s%.2f): %s", $depId, $cur, $wrongAmount, $cur, $correctAmount, $reason);
                $ins = $pdo->prepare("INSERT INTO deposits (mess_id, user_id, amount, entry_type, note, recorded_by, billing_month) VALUES (?, ?, ?, 'correction', ?, ?, ?)");
                $ins->execute([$messId, (int)$orig['user_id'], $diff, $auditNote, $currentUid, $billingMonth]);
                $flash = "Deposit corrected by " . ($diff > 0 ? '+' : '') . "{$cur}" . number_format($diff, 2);
            }
        }
    }

    // --- EXPENSE (GROCERY & MISC) CRUD ---
    if ($action === 'add_expense' && $subInfo['active']) {
        $cat    = $_POST['category'] === 'misc' ? 'misc' : 'grocery';
        $desc   = trim($_POST['description']);
        $amount = (float)$_POST['amount'];
        $status = ($role === 'manager') ? 'approved' : 'pending';
        $paidBy = ($role === 'manager' && !empty($_POST['paid_by'])) ? (int)$_POST['paid_by'] : $currentUid;

        if ($amount > 0 && $desc !== '') {
            $ins = $pdo->prepare("INSERT INTO expenses (mess_id, paid_by_user_id, category, description, amount, expense_date, status, billing_month) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
            $ins->execute([$messId, $paidBy, $cat, $desc, $amount, $today, $status, $billingMonth]);
            $flash = $status === 'approved' ? ucfirst($cat) . " entry added." : "Submitted for Manager approval.";
        }
    }

    if ($action === 'edit_expense' && $role === 'manager' && $subInfo['active']) {
        $expId  = (int)$_POST['expense_id'];
        $desc   = trim($_POST['description']);
        $amount = (float)$_POST['amount'];
        $paidBy = (int)$_POST['paid_by'];
        if ($amount > 0 && $desc !== '') {
            $upd = $pdo->prepare("UPDATE expenses SET description = ?, amount = ?, paid_by_user_id = ? WHERE id = ? AND mess_id = ?");
            $upd->execute([$desc, $amount, $paidBy, $expId, $messId]);
            $flash = "Expense entry updated.";
        }
    }

    if ($action === 'delete_expense' && $role === 'manager' && $subInfo['active']) {
        $expId = (int)$_POST['expense_id'];
        $del = $pdo->prepare("DELETE FROM expenses WHERE id = ? AND mess_id = ?");
        $del->execute([$expId, $messId]);
        $flash = "Expense entry deleted.";
    }

    if ($action === 'decide_expense' && $role === 'manager') {
        $expId  = (int)$_POST['expense_id'];
        $status = $_POST['decision'] === 'approved' ? 'approved' : 'rejected';
        $upd = $pdo->prepare("UPDATE expenses SET status = ? WHERE id = ? AND mess_id = ?");
        $upd->execute([$status, $expId, $messId]);
        $flash = "Expense marked as {$status}.";
    }

    // --- DAILY MEALS ---
    if ($action === 'save_meals' && $role === 'manager' && $subInfo['active']) {
        $mealDate = $_POST['meal_date'] ?? $today;
        foreach ($_POST['meals'] as $uid => $m) {
            $b = (float)($m['b'] ?? 0);
            $l = (float)($m['l'] ?? 0);
            $d = (float)($m['d'] ?? 0);
            $upsert = $pdo->prepare("
                INSERT INTO meals (mess_id, user_id, meal_date, breakfast, lunch, dinner)
                VALUES (?, ?, ?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE breakfast = VALUES(breakfast), lunch = VALUES(lunch), dinner = VALUES(dinner)
            ");
            $upsert->execute([$messId, (int)$uid, $mealDate, $b, $l, $d]);
        }
        $flash = "Daily meals saved for {$mealDate}.";
    }

    // --- SHOPPING ROSTER CRUD ---
    if ($action === 'add_roster' && $role === 'manager' && $subInfo['active']) {
        $assignedUid = (int)$_POST['assigned_user_id'];
        $dutyDate    = $_POST['duty_date'];
        $itemsNote   = trim($_POST['items_note']);
        $ins = $pdo->prepare("INSERT INTO shopping_roster (mess_id, assigned_user_id, duty_date, items_note, status) VALUES (?, ?, ?, ?, 'upcoming')");
        $ins->execute([$messId, $assignedUid, $dutyDate, $itemsNote]);
        $flash = "Shopping duty assigned.";
    }

    if ($action === 'edit_roster' && $role === 'manager' && $subInfo['active']) {
        $rosterId    = (int)$_POST['roster_id'];
        $assignedUid = (int)$_POST['assigned_user_id'];
        $dutyDate    = $_POST['duty_date'];
        $itemsNote   = trim($_POST['items_note']);
        $upd = $pdo->prepare("UPDATE shopping_roster SET assigned_user_id = ?, duty_date = ?, items_note = ? WHERE id = ? AND mess_id = ?");
        $upd->execute([$assignedUid, $dutyDate, $itemsNote, $rosterId, $messId]);
        $flash = "Shopping duty updated.";
    }

    if ($action === 'delete_roster' && $role === 'manager' && $subInfo['active']) {
        $rosterId = (int)$_POST['roster_id'];
        $del = $pdo->prepare("DELETE FROM shopping_roster WHERE id = ? AND mess_id = ?");
        $del->execute([$rosterId, $messId]);
        $flash = "Shopping duty removed.";
    }

    if ($action === 'toggle_roster' && $role === 'manager') {
        $rosterId = (int)$_POST['roster_id'];
        $upd = $pdo->prepare("UPDATE shopping_roster SET status = IF(status='upcoming','completed','upcoming') WHERE id = ? AND mess_id = ?");
        $upd->execute([$rosterId, $messId]);
        $flash = "Shopping duty status toggled.";
    }

    // --- CARRY FORWARD PREVIOUS MONTH BALANCES ---
    if ($action === 'carry_forward_prev' && $role === 'manager' && $subInfo['active']) {
        $membersTmp = $pdo->prepare("SELECT * FROM users WHERE mess_id = ? AND is_active = 1");
        $membersTmp->execute([$messId]);
        $prevSummary = getMonthSummary($pdo, $messId, $prevMonth, $membersTmp->fetchAll());

        foreach ($prevSummary['members'] as $uid => $data) {
            $upd = $pdo->prepare("UPDATE users SET previous_balance = ? WHERE id = ? AND mess_id = ?");
            $upd->execute([round($data['net'], 2), $uid, $messId]);
        }
        $flash = "Carried forward closing balances from {$prevMonth} into each member's Previous Balance!";
    }

    // --- CHAT & NOTIFICATIONS ---
    if ($action === 'send_chat' && $subInfo['active']) {
        $msg     = trim($_POST['message']);
        $reqType = $_POST['request_type'] === 'meal_request' ? 'meal_request' : 'chat';
        if ($msg !== '') {
            $ins = $pdo->prepare("INSERT INTO chat_messages (mess_id, sender_id, message, request_type) VALUES (?, ?, ?, ?)");
            $ins->execute([$messId, $currentUid, $msg, $reqType]);
        }
    }

    if ($action === 'mark_chat_done' && $role === 'manager') {
        $chatId = (int)$_POST['chat_id'];
        $upd = $pdo->prepare("UPDATE chat_messages SET is_processed = 1 WHERE id = ? AND mess_id = ?");
        $upd->execute([$chatId, $messId]);
        $flash = "Notification marked as handled.";
    }

    if ($action === 'activate_plan' && $role === 'manager') {
        $plan   = $_POST['plan'] === 'pro' ? 'pro' : 'starter';
        $newEnd = date('Y-m-d H:i:s', strtotime('+30 days'));
        $upd = $pdo->prepare("UPDATE messes SET subscription_plan = ?, subscription_status = 'active', subscription_ends_at = ? WHERE id = ?");
        $upd->execute([$plan, $newEnd, $messId]);
        header("Location: dashboard.php");
        exit;
    }
}

// --- FETCH MEMBERS & COMPUTE CURRENT + PREVIOUS MONTH SUMMARIES ---
$membersStmt = $pdo->prepare("SELECT * FROM users WHERE mess_id = ? AND is_active = 1 ORDER BY member_code ASC");
$membersStmt->execute([$messId]);
$members = $membersStmt->fetchAll();

$currentUserRecord = null;
foreach ($members as $m) {
    if ((int)$m['id'] === $currentUid) {
        $currentUserRecord = $m;
        break;
    }
}

$currSummary = getMonthSummary($pdo, $messId, $billingMonth, $members);
$prevSummary = getMonthSummary($pdo, $messId, $prevMonth, $members);

// Expenses Lists
$expStmt = $pdo->prepare("
    SELECT e.*, u.full_name, u.member_code
    FROM expenses e JOIN users u ON e.paid_by_user_id = u.id
    WHERE e.mess_id = ? AND e.billing_month = ?
    ORDER BY e.created_at DESC
");
$expStmt->execute([$messId, $billingMonth]);
$allExpenses = $expStmt->fetchAll();

$groceryLogs = array_filter($allExpenses, fn($e) => $e['category'] === 'grocery');
$miscLogs    = array_filter($allExpenses, fn($e) => $e['category'] === 'misc');
$pendingExpenses = array_filter($allExpenses, fn($e) => $e['status'] === 'pending');

// Deposits List
$depStmt = $pdo->prepare("
    SELECT d.*, u.full_name, u.member_code
    FROM deposits d JOIN users u ON d.user_id = u.id
    WHERE d.mess_id = ? AND d.billing_month = ?
    ORDER BY d.created_at DESC
");
$depStmt->execute([$messId, $billingMonth]);
$allDeposits = $depStmt->fetchAll();

// Selected Date Meals
$selectedMealDate = $_GET['meal_date'] ?? $today;
$todayMealStmt = $pdo->prepare("SELECT * FROM meals WHERE mess_id = ? AND meal_date = ?");
$todayMealStmt->execute([$messId, $selectedMealDate]);
$todayMealsMap = [];
foreach ($todayMealStmt->fetchAll() as $tm) {
    $todayMealsMap[$tm['user_id']] = $tm;
}

// Full Month Meal Chart Matrix (Day 1 to Days in Month)
$daysInMonth = (int)date('t', strtotime($billingMonth . '-01'));
$monthMealsStmt = $pdo->prepare("
    SELECT user_id, DAY(meal_date) AS day_num, (breakfast + lunch + dinner) AS day_total
    FROM meals WHERE mess_id = ? AND DATE_FORMAT(meal_date, '%Y-%m') = ?
");
$monthMealsStmt->execute([$messId, $billingMonth]);
$mealMatrix = [];
foreach ($monthMealsStmt->fetchAll() as $mm) {
    $mealMatrix[$mm['user_id']][(int)$mm['day_num']] = (float)$mm['day_total'];
}

// Shopping Roster
$rosterStmt = $pdo->prepare("
    SELECT r.*, u.full_name, u.member_code
    FROM shopping_roster r JOIN users u ON r.assigned_user_id = u.id
    WHERE r.mess_id = ? ORDER BY r.duty_date ASC
");
$rosterStmt->execute([$messId]);
$rosters = $rosterStmt->fetchAll();

// Chat & Unprocessed Notifications
$chatStmt = $pdo->prepare("
    SELECT c.*, u.full_name, u.member_code
    FROM chat_messages c JOIN users u ON c.sender_id = u.id
    WHERE c.mess_id = ? ORDER BY c.created_at DESC LIMIT 30
");
$chatStmt->execute([$messId]);
$chats = array_reverse($chatStmt->fetchAll());

$unreadMealRequests = array_filter($chats, fn($c) => $c['request_type'] === 'meal_request' && (int)$c['is_processed'] === 0);
$totalNotifications = count($pendingExpenses) + count($unreadMealRequests);

// Column Totals
$sumPrevBal  = 0.0;
$sumDeposit  = 0.0;
$sumOwnShop  = 0.0;
$sumMeals    = 0.0;
$sumMealCost = 0.0;
$sumMisc     = 0.0;
$sumNetBal   = 0.0;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($mess['mess_name']) ?> — MessMate</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <style>
        body { background-color: #f8fafc; color: #1e293b; font-size: 0.97rem; }
        .card-clean { background: #fff; border: 1px solid #e2e8f0; border-radius: 1rem; box-shadow: 0 1px 3px rgba(0,0,0,0.03); }
        .kpi-label { font-size: 0.75rem; font-weight: 700; letter-spacing: 0.02em; text-transform: uppercase; color: #00204e; }
        .kpi-value { font-size: 1.55rem; font-weight: 700; margin-top: 0.25rem; }
        .nav-tabs-clean .nav-link { color: #475569; font-weight: 600; font-size: 0.95rem; border: none; border-bottom: 3px solid transparent; padding: 0.85rem 1.2rem; }
        .nav-tabs-clean .nav-link.active { color: #059669; border-bottom-color: #059669; background: transparent; }

        /* Borderless Zebra Ledger Table with Taller Rows */
        .table-ledger { border-collapse: collapse; }
        .table-ledger thead th {
            font-size: 0.78rem; text-transform: uppercase; color: #475569;
            background: #e2e8f0; font-weight: 700; border: none; padding: 0.95rem 0.85rem;
        }
        .table-ledger tbody tr { border: none; }
        .table-ledger tbody tr:nth-child(odd) { background-color: #ffffff; }
        .table-ledger tbody tr:nth-child(even) { background-color: #f1f5f9; }
        .table-ledger tbody tr.row-you { background-color: #ecfdf5 !important; }
        .table-ledger tbody td { border: none; padding: 1rem 0.85rem; vertical-align: middle; font-size: 0.96rem; }
        .table-ledger tfoot td { border: none; background-color: #e2e8f0; padding: 1rem 0.85rem; font-size: 0.96rem; }
        .net-pill { display: inline-block; padding: 0.35rem 0.75rem; border-radius: 0.55rem; font-weight: 700; font-family: monospace; font-size: 0.96rem; }

        /* Monthly Meal Chart Matrix */
        .meal-matrix th, .meal-matrix td { padding: 0.55rem 0.4rem; text-align: center; font-size: 0.85rem; border: none; }
        .meal-matrix tbody tr:nth-child(odd) { background: #fff; }
        .meal-matrix tbody tr:nth-child(even) { background: #f8fafc; }
        .sticky-col { position: sticky; left: 0; z-index: 2; background: inherit; text-align: left !important; padding-left: 0.85rem !important; min-width: 155px; }
    </style>
</head>
<body>

<!-- Top Navbar -->
<nav class="navbar navbar-expand-lg bg-white border-bottom sticky-top py-2.5">
    <div class="container-xl">
        <div class="d-flex align-items-center gap-2">
            <div class="bg-success text-white rounded-3 d-flex align-items-center justify-content-center fw-bold fs-5" style="width:40px;height:40px;">M</div>
            <div>
                <div class="d-flex align-items-center gap-2">
                    <span class="fw-bold text-dark fs-6"><?= e($mess['mess_name']) ?></span>
                    <span class="badge bg-light text-dark border font-monospace"><?= e($mess['mess_code']) ?></span>
                </div>
                <div class="small text-muted">
                    User: <strong><?= e($currentUserRecord['full_name'] ?? '') ?></strong> (<span class="text-success text-uppercase fw-bold"><?= e($role) ?></span>) •
                    <span class="<?= $subInfo['active'] ? 'text-success' : 'text-danger fw-bold' ?>"><?= e($subInfo['label']) ?></span>
                </div>
            </div>
        </div>

        <div class="d-flex align-items-center gap-2 flex-wrap">
            <!-- Month Switcher -->
            <form method="GET" class="d-flex align-items-center">
                <input type="hidden" name="tab" value="<?= e($activeTab) ?>">
                <input type="month" name="month" value="<?= e($billingMonth) ?>" onchange="this.form.submit()" class="form-control form-control-sm fw-semibold">
            </form>

            <!-- Notification & Chat Drawer Button -->
            <button class="btn btn-outline-secondary btn-sm position-relative fw-semibold py-1.5 px-2.5" type="button" data-bs-toggle="offcanvas" data-bs-target="#drawerNotify">
                <i class="bi bi-bell-fill"></i> Chat & Approvals
                <?php if ($totalNotifications > 0): ?>
                    <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger">
                        <?= $totalNotifications ?>
                    </span>
                <?php endif; ?>
            </button>

            <?php if ($role === 'manager'): ?>
                <button class="btn btn-outline-warning text-dark btn-sm fw-semibold py-1.5" data-bs-toggle="modal" data-bs-target="#modalSub">
                    <i class="bi bi-star-fill text-warning"></i> Plan
                </button>
                <button class="btn btn-success btn-sm fw-semibold py-1.5" data-bs-toggle="modal" data-bs-target="#modalDeposit">
                    <i class="bi bi-plus-lg"></i> Deposit
                </button>
                <button class="btn btn-dark btn-sm fw-semibold py-1.5" data-bs-toggle="modal" data-bs-target="#modalMember">
                    <i class="bi bi-person-plus-fill"></i> Member
                </button>
            <?php endif; ?>
            <a href="logout.php" class="btn btn-light border btn-sm text-secondary fw-semibold py-1.5">Logout</a>
        </div>
    </div>
</nav>

<div class="container-xl py-4">

    <?php if (!$subInfo['active']): ?>
        <div class="alert alert-danger d-flex justify-content-between align-items-center rounded-4">
            <div><strong>Subscription Expired!</strong> Dashboard is in read-only mode until renewed.</div>
            <?php if ($role === 'manager'): ?>
                <button class="btn btn-light btn-sm fw-bold text-danger" data-bs-toggle="modal" data-bs-target="#modalSub">Renew Plan</button>
            <?php endif; ?>
        </div>
    <?php endif; ?>

    <?php if ($flash): ?>
        <div class="alert alert-success alert-dismissible fade show rounded-3 py-2.5 fw-semibold" role="alert">
            <i class="bi bi-check-circle-fill me-1"></i> <?= e($flash) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <!-- INDIVIDUAL MEMBER SUMMARY CARD (SHOWN WHEN LOGGED IN AS MEMBER) -->
    <?php if ($role === 'member' && isset($currSummary['members'][$currentUid])):
        $my = $currSummary['members'][$currentUid];
    ?>
    <div class="card border-2 border-success rounded-4 p-4 mb-4 bg-white shadow-sm">
        <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
            <div>
                <span class="badge bg-success text-white mb-1">MY PERSONAL ACCOUNT (<?= e($billingMonth) ?>)</span>
                <h5 class="fw-bold mb-0"><?= e($currentUserRecord['full_name']) ?> <span class="text-muted font-monospace fs-6">(<?= e($currentUserRecord['member_code']) ?>)</span></h5>
            </div>
            <div class="text-end">
                <div class="small text-muted fw-semibold text-uppercase">My Net Balance</div>
                <span class="net-pill fs-5 <?= $my['net'] >= 0 ? 'bg-success-subtle text-success' : 'bg-danger-subtle text-danger' ?>">
                    <?= ($my['net'] >= 0 ? '+' : '') . e($cur) . number_format($my['net'], 2) ?>
                </span>
            </div>
        </div>
        <div class="row g-3 text-center">
            <div class="col-6 col-md-2">
                <div class="p-2.5 rounded-3 bg-light">
                    <div class="small text-muted">Prev. Balance</div>
                    <div class="fw-bold font-monospace"><?= ($my['prev'] >= 0 ? '+' : '') . e($cur) . number_format($my['prev'], 2) ?></div>
                </div>
            </div>
            <div class="col-6 col-md-2">
                <div class="p-2.5 rounded-3 bg-light">
                    <div class="small text-muted">My Deposits</div>
                    <div class="fw-bold font-monospace text-success"><?= e($cur) . number_format($my['deposit'], 2) ?></div>
                </div>
            </div>
            <div class="col-6 col-md-2">
                <div class="p-2.5 rounded-3 bg-light">
                    <div class="small text-muted">Own Grocery</div>
                    <div class="fw-bold font-monospace"><?= e($cur) . number_format($my['grocery'], 2) ?></div>
                </div>
            </div>
            <div class="col-6 col-md-2">
                <div class="p-2.5 rounded-3 bg-light">
                    <div class="small text-muted">My Meals</div>
                    <div class="fw-bold"><?= number_format($my['meals'], 1) ?></div>
                </div>
            </div>
            <div class="col-6 col-md-2">
                <div class="p-2.5 rounded-3 bg-light">
                    <div class="small text-muted">My Meal Cost</div>
                    <div class="fw-bold font-monospace"><?= e($cur) . number_format($my['meal_cost'], 2) ?></div>
                </div>
            </div>
            <div class="col-6 col-md-2">
                <div class="p-2.5 rounded-3 bg-light">
                    <div class="small text-muted">My Misc Share</div>
                    <div class="fw-bold font-monospace"><?= e($cur) . number_format($my['misc'], 2) ?></div>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <?php if (!empty($flashError)): ?>
        <div class="alert alert-danger alert-dismissible fade show rounded-3 py-2.5 fw-semibold" role="alert">
            <i class="bi bi-shield-exclamation me-1"></i> <?= e($flashError) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <!-- 5 KPI Summary Cards -->
    <div class="row g-3 mb-4">
        <div class="col-6 col-lg">
            <div class="card-clean p-4 h-100 border-dark">
                <div class="kpi-label">Total Funds Collected</div>
                <div class="kpi-value text-dark"><?= e($cur) ?><?= number_format($currSummary['total_deposits'], 2) ?></div>
            </div>
        </div>
        <div class="col-6 col-lg">
            <div class="card-clean p-4 h-100 border-warning">
                <div class="kpi-label">Grocery Expenditure</div>
                <div class="kpi-value text-warning"><?= e($cur) ?><?= number_format($currSummary['total_grocery'], 2) ?></div>
            </div>
        </div>
        <div class="col-6 col-lg">
            <div class="card-clean p-4 h-100 border-primary">
                <div class="kpi-label">Misc & Fixed Bills</div>
                <div class="kpi-value text-primary"><?= e($cur) ?><?= number_format($currSummary['total_misc'], 2) ?></div>
            </div>
        </div>
        <div class="col-6 col-lg">
            <div class="card-clean p-4 h-100 border-success">
                <div class="kpi-label">Cash Held by Manager</div>
                <div class="kpi-value <?= $currSummary['cash_held'] >= 0 ? 'text-success' : 'text-danger' ?>">
                    <?= e($cur) ?><?= number_format($currSummary['cash_held'], 2) ?>
                </div>
            </div>
        </div>
        <div class="col-12 col-lg">
            <div class="card border-0 rounded-4 p-4 h-100 text-white" style="background:#064e3b;">
                <div class="kpi-label text-white-50">Live Cost Per Meal</div>
                <div class="kpi-value"><?= e($cur) ?><?= number_format($currSummary['cost_per_meal'], 2) ?></div>
                <div class="small text-white-50">Total Meals: <?= number_format($currSummary['total_meals'], 1) ?></div>
            </div>
        </div>
    </div>

    <!-- Navigation Tabs -->
    <ul class="nav nav-tabs nav-tabs-clean border-bottom mb-4" role="tablist">
        <li class="nav-item">
            <button class="nav-link <?= $activeTab === 'overview' ? 'active' : '' ?>" data-bs-toggle="tab" data-bs-target="#tabOverview" type="button">
                <i class="bi bi-table me-1"></i> Overview & Ledger
            </button>
        </li>
        <li class="nav-item">
            <button class="nav-link <?= $activeTab === 'meals' ? 'active' : '' ?>" data-bs-toggle="tab" data-bs-target="#tabMeals" type="button">
                <i class="bi bi-egg-fried me-1"></i> Daily & Full Month Meals
            </button>
        </li>
        <li class="nav-item">
            <button class="nav-link <?= $activeTab === 'expenses' ? 'active' : '' ?>" data-bs-toggle="tab" data-bs-target="#tabExpenses" type="button">
                <i class="bi bi-cart4 me-1"></i> Grocery & Misc Bills
                <?php if (count($pendingExpenses) > 0): ?>
                    <span class="badge bg-danger ms-1"><?= count($pendingExpenses) ?></span>
                <?php endif; ?>
            </button>
        </li>
        <li class="nav-item">
            <button class="nav-link <?= $activeTab === 'roster' ? 'active' : '' ?>" data-bs-toggle="tab" data-bs-target="#tabRoster" type="button">
                <i class="bi bi-calendar-check me-1"></i> Shopping Roster
            </button>
        </li>
        <li class="nav-item">
            <button class="nav-link <?= $activeTab === 'prevmonth' ? 'active' : '' ?>" data-bs-toggle="tab" data-bs-target="#tabPrevMonth" type="button">
                <i class="bi bi-clock-history me-1"></i> Previous Month (<?= e($prevMonth) ?>)
            </button>
        </li>
    </ul>

    <div class="tab-content">

        <!-- TAB 1: OVERVIEW & BORDERLESS ZEBRA MEMBER LEDGER -->
        <div class="tab-pane fade <?= $activeTab === 'overview' ? 'show active' : '' ?>" id="tabOverview">
            <div class="card-clean overflow-hidden mb-4">
                <div class="p-4 d-flex justify-content-between align-items-center flex-wrap gap-2">
                    <div>
                        <h5 class="fw-bold mb-0">Member Accounts & Meal Ledger (<?= e($billingMonth) ?>)</h5>
                        <small class="text-muted">Alternating shaded rows • Net Balance highlighted with color badges.</small>
                    </div>
                    <span class="badge bg-light text-secondary border fs-6 py-2 px-3">Misc Split: <?= e($cur) ?><?= number_format($currSummary['misc_per_member'], 2) ?> / member</span>
                </div>
                <div class="table-responsive">
                    <table class="table table-ledger mb-0">
                        <thead>
                            <tr>
                                <th class="ps-4">Member</th>
                                <th class="text-end">Prev. Balance</th>
                                <th class="text-end">Deposited</th>
                                <th class="text-end">Own Grocery</th>
                                <th class="text-end">Meals</th>
                                <th class="text-end">Meal Cost</th>
                                <th class="text-end">Misc Share</th>
                                <th class="text-end">Net Balance</th>
                                <?php if ($role === 'manager'): ?><th class="text-end pe-4">Manage</th><?php endif; ?>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($members as $m):
                                $uid   = (int)$m['id'];
                                $isYou = ($uid === $currentUid);
                                $d     = $currSummary['members'][$uid];

                                $sumPrevBal  += $d['prev'];
                                $sumDeposit  += $d['deposit'];
                                $sumOwnShop  += $d['grocery'];
                                $sumMeals    += $d['meals'];
                                $sumMealCost += $d['meal_cost'];
                                $sumMisc     += $d['misc'];
                                $sumNetBal   += $d['net'];
                            ?>
                            <tr class="<?= $isYou ? 'row-you' : '' ?>">
                                <td class="ps-4">
                                    <span class="fw-bold text-dark"><?= e($m['full_name']) ?></span>
                                    <span class="badge bg-white text-secondary border font-monospace ms-1"><?= e($m['member_code']) ?></span>
                                    <?php if ($isYou): ?>
                                        <span class="badge bg-success text-white ms-1">You</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-end font-monospace <?= $d['prev'] < 0 ? 'text-danger' : 'text-secondary' ?>">
                                    <?= ($d['prev'] >= 0 ? '+' : '') . number_format($d['prev'], 2) ?>
                                </td>
                                <td class="text-end font-monospace text-success fw-semibold">
                                    <?= number_format($d['deposit'], 2) ?>
                                </td>
                                <td class="text-end font-monospace"><?= number_format($d['grocery'], 2) ?></td>
                                <td class="text-end fw-bold"><?= number_format($d['meals'], 1) ?></td>
                                <td class="text-end font-monospace"><?= number_format($d['meal_cost'], 2) ?></td>
                                <td class="text-end font-monospace text-muted"><?= number_format($d['misc'], 2) ?></td>
                                <td class="text-end">
                                    <span class="net-pill <?= $d['net'] >= 0 ? 'bg-success-subtle text-success' : 'bg-danger-subtle text-danger' ?>">
                                        <?= ($d['net'] >= 0 ? '+' : '') . e($cur) . number_format($d['net'], 2) ?>
                                    </span>
                                </td>
                                <?php if ($role === 'manager'): ?>
                                <td class="text-end pe-4">
                                    <button type="button" class="btn btn-sm btn-outline-secondary py-1 px-2"
                                        onclick="openEditMember(<?= $uid ?>, '<?= e($m['full_name']) ?>', <?= (float)$m['previous_balance'] ?>)">
                                        <i class="bi bi-pencil"></i>
                                    </button>
                                    <?php
                                    $hasMonthActivity = (
                                        ($currSummary['members'][$uid]['deposit'] ?? 0) != 0 ||
                                        ($currSummary['members'][$uid]['meals'] ?? 0) > 0 ||
                                        ($currSummary['members'][$uid]['grocery'] ?? 0) > 0
                                    );
                                    ?>
                                    <?php if ($m['role'] !== 'manager'): ?>
                                        <?php if ($hasMonthActivity): ?>
                                            <button type="button" class="btn btn-sm btn-outline-secondary py-1 px-2 disabled"
                                                title="Has meals, deposits, or expenses in <?= e($billingMonth) ?>" aria-disabled="true">
                                                <i class="bi bi-lock-fill"></i>
                                            </button>
                                        <?php else: ?>
                                            <form method="POST" class="d-inline" onsubmit="return confirm('Remove <?= e($m['full_name']) ?> from active mess?');">
                                                <input type="hidden" name="action" value="delete_member">
                                                <input type="hidden" name="user_id" value="<?= $uid ?>">
                                                <button type="submit" class="btn btn-sm btn-outline-danger py-1 px-2"><i class="bi bi-trash"></i></button>
                                            </form>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                </td>
                                <?php endif; ?>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                        <tfoot class="fw-bold">
                            <tr>
                                <td class="ps-4 text-uppercase text-secondary">Total (<?= count($members) ?> Members)</td>
                                <td class="text-end font-monospace"><?= ($sumPrevBal >= 0 ? '+' : '') . number_format($sumPrevBal, 2) ?></td>
                                <td class="text-end font-monospace text-success"><?= number_format($sumDeposit, 2) ?></td>
                                <td class="text-end font-monospace"><?= number_format($sumOwnShop, 2) ?></td>
                                <td class="text-end"><?= number_format($sumMeals, 1) ?></td>
                                <td class="text-end font-monospace"><?= number_format($sumMealCost, 2) ?></td>
                                <td class="text-end font-monospace"><?= number_format($sumMisc, 2) ?></td>
                                <td class="text-end">
                                    <span class="net-pill <?= $sumNetBal >= 0 ? 'bg-success text-white' : 'bg-danger text-white' ?>">
                                        <?= ($sumNetBal >= 0 ? '+' : '') . e($cur) . number_format($sumNetBal, 2) ?>
                                    </span>
                                </td>
                                <?php if ($role === 'manager'): ?><td></td><?php endif; ?>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>

            <!-- Deposit History & Easy One-Click Fix Card -->
            <div class="card-clean p-4">
                <div class="mb-3">
                    <h6 class="fw-bold mb-0">Deposit History & Balance Correction Ledger</h6>
                    <small class="text-muted">Click <strong>Fix</strong> next to any deposit to enter the true amount—adjustment math is automatic.</small>
                </div>
                <div class="table-responsive">
                    <table class="table align-middle mb-0">
                        <thead class="table-light small text-uppercase text-muted">
                            <tr>
                                <th>Date</th>
                                <th>Member</th>
                                <th>Type</th>
                                <th>Note / Audit Reason</th>
                                <th class="text-end">Amount</th>
                                <?php if ($role === 'manager'): ?><th class="text-end">Action</th><?php endif; ?>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($allDeposits as $dp): $isCorr = $dp['entry_type'] === 'correction'; ?>
                            <tr class="<?= $isCorr ? 'table-warning' : '' ?>">
                                <td class="text-muted small"><?= date('M d, H:i', strtotime($dp['created_at'])) ?></td>
                                <td class="fw-semibold"><?= e($dp['full_name']) ?> <span class="text-muted">(<?= e($dp['member_code']) ?>)</span></td>
                                <td>
                                    <span class="badge <?= $isCorr ? 'bg-warning text-dark' : 'bg-success-subtle text-success' ?>">
                                        <?= $isCorr ? 'Correction' : 'Deposit' ?>
                                    </span>
                                </td>
                                <td><?= e($dp['note']) ?></td>
                                <td class="text-end font-monospace fw-bold <?= $dp['amount'] < 0 ? 'text-danger' : 'text-success' ?>">
                                    <?= ($dp['amount'] >= 0 ? '+' : '') . e($cur) . number_format($dp['amount'], 2) ?>
                                </td>
                                <?php if ($role === 'manager'): ?>
                                <td class="text-end">
                                    <?php if (!$isCorr): ?>
                                        <button type="button" class="btn btn-outline-danger btn-sm py-1 px-2"
                                            onclick="openFixModal(<?= $dp['id'] ?>, '<?= e($dp['full_name']) ?>', <?= (float)$dp['amount'] ?>)">
                                            <i class="bi bi-pencil-square"></i> Fix
                                        </button>
                                    <?php else: ?>
                                        <span class="text-muted">—</span>
                                    <?php endif; ?>
                                </td>
                                <?php endif; ?>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- TAB 2: DAILY MEALS + FULL MONTH MEAL CHART -->
        <div class="tab-pane fade <?= $activeTab === 'meals' ? 'show active' : '' ?>" id="tabMeals">
            <!-- Daily Entry Form -->
            <div class="card-clean p-4 mb-4">
                <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
                    <div>
                        <h5 class="fw-bold mb-0">Daily Meal Entry (<?= e($selectedMealDate) ?>)</h5>
                        <small class="text-muted">Breakfast = 0.5 • Lunch = 1.0 • Dinner = 1.0</small>
                    </div>
                    <form method="GET" class="d-flex gap-2">
                        <input type="hidden" name="tab" value="meals">
                        <input type="hidden" name="month" value="<?= e($billingMonth) ?>">
                        <input type="date" name="meal_date" value="<?= e($selectedMealDate) ?>" class="form-control form-control-sm">
                        <button type="submit" class="btn btn-outline-secondary btn-sm">Load Date</button>
                    </form>
                </div>

                <form method="POST">
                    <input type="hidden" name="action" value="save_meals">
                    <input type="hidden" name="current_tab" value="meals">
                    <input type="hidden" name="meal_date" value="<?= e($selectedMealDate) ?>">
                    <div class="table-responsive">
                        <table class="table align-middle">
                            <thead class="table-light small text-uppercase">
                                <tr>
                                    <th>Member</th>
                                    <th class="text-center" style="width:140px;">Breakfast (0.5)</th>
                                    <th class="text-center" style="width:140px;">Lunch (1.0)</th>
                                    <th class="text-center" style="width:140px;">Dinner (1.0)</th>
                                    <th class="text-end">Day Total</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($members as $m):
                                    $uid = (int)$m['id'];
                                    $tm  = $todayMealsMap[$uid] ?? ['breakfast' => 0.0, 'lunch' => 1.0, 'dinner' => 1.0];
                                    $daySum = (float)$tm['breakfast'] + (float)$tm['lunch'] + (float)$tm['dinner'];
                                ?>
                                <tr>
                                    <td class="fw-semibold"><?= e($m['full_name']) ?> <span class="badge bg-light text-dark border font-monospace"><?= e($m['member_code']) ?></span></td>
                                    <td><input type="number" step="0.5" min="0" max="5" name="meals[<?= $uid ?>][b]" value="<?= $tm['breakfast'] ?>" <?= $role !== 'manager' ? 'disabled' : '' ?> class="form-control text-center"></td>
                                    <td><input type="number" step="0.5" min="0" max="5" name="meals[<?= $uid ?>][l]" value="<?= $tm['lunch'] ?>" <?= $role !== 'manager' ? 'disabled' : '' ?> class="form-control text-center"></td>
                                    <td><input type="number" step="0.5" min="0" max="5" name="meals[<?= $uid ?>][d]" value="<?= $tm['dinner'] ?>" <?= $role !== 'manager' ? 'disabled' : '' ?> class="form-control text-center"></td>
                                    <td class="text-end fw-bold"><?= number_format($daySum, 1) ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php if ($role === 'manager'): ?>
                        <button type="submit" class="btn btn-success fw-semibold px-4">Save Meal Records</button>
                    <?php endif; ?>
                </form>
            </div>

            <!-- FULL MONTH MEAL CHART MATRIX (DAY 1 TO 30/31) -->
            <div class="card-clean p-4">
                <div class="mb-3">
                    <h5 class="fw-bold mb-0">Full Month Meal Chart (<?= e($billingMonth) ?>)</h5>
                    <small class="text-muted">Complete day-by-day meal breakdown (Day 1 to <?= $daysInMonth ?>) for every member.</small>
                </div>
                <div class="table-responsive">
                    <table class="table meal-matrix align-middle mb-0">
                        <thead class="table-dark">
                            <tr>
                                <th class="sticky-col bg-dark text-white">Member</th>
                                <?php for ($d = 1; $d <= $daysInMonth; $d++): ?>
                                    <th><?= $d ?></th>
                                <?php endfor; ?>
                                <th class="bg-success text-white">Total</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            $dailyColTotals = array_fill(1, $daysInMonth, 0.0);
                            foreach ($members as $m):
                                $uid = (int)$m['id'];
                                $rowSum = 0.0;
                            ?>
                            <tr>
                                <td class="sticky-col fw-semibold"><?= e($m['full_name']) ?></td>
                                <?php for ($d = 1; $d <= $daysInMonth; $d++):
                                    $val = $mealMatrix[$uid][$d] ?? 0.0;
                                    $rowSum += $val;
                                    $dailyColTotals[$d] += $val;
                                ?>
                                    <td class="<?= $val > 0 ? 'fw-semibold text-dark' : 'text-muted opacity-50' ?>">
                                        <?= $val > 0 ? number_format($val, 1) : '0' ?>
                                    </td>
                                <?php endfor; ?>
                                <td class="fw-bold bg-success-subtle text-success"><?= number_format($rowSum, 1) ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                        <tfoot class="table-secondary fw-bold">
                            <tr>
                                <td class="sticky-col bg-secondary text-white">Daily Total</td>
                                <?php for ($d = 1; $d <= $daysInMonth; $d++): ?>
                                    <td><?= number_format($dailyColTotals[$d], 1) ?></td>
                                <?php endfor; ?>
                                <td class="bg-dark text-white"><?= number_format(array_sum($dailyColTotals), 1) ?></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
        </div>

        <!-- TAB 3: GROCERY & MISC BILLS (WITH EDIT & DELETE) -->
        <div class="tab-pane fade <?= $activeTab === 'expenses' ? 'show active' : '' ?>" id="tabExpenses">
            <div class="row g-4">
                <!-- Left Column: Grocery -->
                <div class="col-lg-6">
                    <div class="card-clean p-4 mb-4">
                        <h6 class="fw-bold mb-1"><i class="bi bi-bag-plus-fill text-warning me-1"></i> Log Grocery / Bazar Purchase</h6>
                        <p class="small text-muted mb-3">Included in live cost-per-meal calculation once approved.</p>
                        <form method="POST" class="row g-2">
                            <input type="hidden" name="action" value="add_expense">
                            <input type="hidden" name="current_tab" value="expenses">
                            <input type="hidden" name="category" value="grocery">
                            <?php if ($role === 'manager'): ?>
                            <div class="col-6">
                                <select name="paid_by" class="form-select">
                                    <?php foreach ($members as $m): ?>
                                        <option value="<?= $m['id'] ?>" <?= $m['id'] == $currentUid ? 'selected' : '' ?>>Paid by: <?= e($m['full_name']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-6">
                                <input type="number" step="0.01" name="amount" required placeholder="Amount (<?= e($cur) ?>)" class="form-control">
                            </div>
                            <?php else: ?>
                            <div class="col-12">
                                <input type="number" step="0.01" name="amount" required placeholder="Amount Spent (<?= e($cur) ?>)" class="form-control">
                            </div>
                            <?php endif; ?>
                            <div class="col-12">
                                <input type="text" name="description" required placeholder="Items purchased (e.g., Rice 10kg, Chicken, Vegetables)" class="form-control">
                            </div>
                            <div class="col-12">
                                <button type="submit" class="btn btn-warning fw-semibold w-100">
                                    <?= $role === 'manager' ? 'Add & Approve Grocery Purchase' : 'Submit Grocery Expense for Approval' ?>
                                </button>
                            </div>
                        </form>
                    </div>

                    <div class="card-clean p-4">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <h6 class="fw-bold mb-0">Grocery Expenditure Log</h6>
                            <span class="badge bg-warning-subtle text-dark fs-6">Total: <?= e($cur) ?><?= number_format($currSummary['total_grocery'], 2) ?></span>
                        </div>
                        <div class="table-responsive" style="max-height:360px;">
                            <table class="table align-middle mb-0">
                                <thead class="table-light small">
                                    <tr>
                                        <th>Items</th>
                                        <th>Buyer</th>
                                        <th>Status</th>
                                        <th class="text-end">Amount</th>
                                        <?php if ($role === 'manager'): ?><th class="text-end">Edit/Del</th><?php endif; ?>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($groceryLogs as $g): ?>
                                    <tr>
                                        <td><?= e($g['description']) ?><br><span class="text-muted small"><?= e($g['expense_date']) ?></span></td>
                                        <td><?= e($g['full_name']) ?></td>
                                        <td>
                                            <span class="badge <?= $g['status'] === 'approved' ? 'bg-success-subtle text-success' : ($g['status'] === 'pending' ? 'bg-warning text-dark' : 'bg-danger-subtle text-danger') ?>">
                                                <?= e($g['status']) ?>
                                            </span>
                                        </td>
                                        <td class="text-end font-monospace fw-bold"><?= e($cur) ?><?= number_format($g['amount'], 2) ?></td>
                                        <?php if ($role === 'manager'): ?>
                                        <td class="text-end text-nowrap">
                                            <button type="button" class="btn btn-sm btn-outline-secondary py-0 px-1.5"
                                                onclick="openEditExpense(<?= $g['id'] ?>, '<?= e($g['description']) ?>', <?= (float)$g['amount'] ?>, <?= (int)$g['paid_by_user_id'] ?>, 'expenses')">
                                                <i class="bi bi-pencil"></i>
                                            </button>
                                            <form method="POST" class="d-inline" onsubmit="return confirm('Delete this grocery entry?');">
                                                <input type="hidden" name="action" value="delete_expense">
                                                <input type="hidden" name="current_tab" value="expenses">
                                                <input type="hidden" name="expense_id" value="<?= $g['id'] ?>">
                                                <button type="submit" class="btn btn-sm btn-outline-danger py-0 px-1.5"><i class="bi bi-trash"></i></button>
                                            </form>
                                        </td>
                                        <?php endif; ?>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- Right Column: Misc & Fixed Bills -->
                <div class="col-lg-6">
                    <div class="card-clean p-4">
                        <h6 class="fw-bold mb-1"><i class="bi bi-receipt text-primary me-1"></i> Miscellaneous & Fixed Bills</h6>
                        <p class="small text-muted mb-3">Cook salary, Wi-Fi, gas, electricity — split equally across all <?= count($members) ?> members (<?= e($cur) ?><?= number_format($currSummary['misc_per_member'], 2) ?> each).</p>
                        <form method="POST" class="row g-2 mb-4">
                            <input type="hidden" name="action" value="add_expense">
                            <input type="hidden" name="current_tab" value="expenses">
                            <input type="hidden" name="category" value="misc">
                            <div class="col-7">
                                <input type="text" name="description" required placeholder="Bill name (e.g., Cook Salary, Wi-Fi)" class="form-control">
                            </div>
                            <div class="col-5">
                                <input type="number" step="0.01" name="amount" required placeholder="Amount (<?= e($cur) ?>)" class="form-control">
                            </div>
                            <div class="col-12">
                                <button type="submit" class="btn btn-primary fw-semibold w-100">Add Misc / Fixed Bill</button>
                            </div>
                        </form>

                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <span class="small fw-bold text-uppercase text-muted">Misc Bills Log</span>
                            <span class="badge bg-primary-subtle text-primary fs-6">Total: <?= e($cur) ?><?= number_format($currSummary['total_misc'], 2) ?></span>
                        </div>
                        <div class="table-responsive" style="max-height:360px;">
                            <table class="table align-middle mb-0">
                                <thead class="table-light small">
                                    <tr>
                                        <th>Bill Description</th>
                                        <th>Paid By</th>
                                        <th class="text-end">Amount</th>
                                        <?php if ($role === 'manager'): ?><th class="text-end">Edit/Del</th><?php endif; ?>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($miscLogs as $ms): ?>
                                    <tr>
                                        <td><?= e($ms['description']) ?></td>
                                        <td><?= e($ms['full_name']) ?></td>
                                        <td class="text-end font-monospace fw-bold"><?= e($cur) ?><?= number_format($ms['amount'], 2) ?></td>
                                        <?php if ($role === 'manager'): ?>
                                        <td class="text-end text-nowrap">
                                            <button type="button" class="btn btn-sm btn-outline-secondary py-0 px-1.5"
                                                onclick="openEditExpense(<?= $ms['id'] ?>, '<?= e($ms['description']) ?>', <?= (float)$ms['amount'] ?>, <?= (int)$ms['paid_by_user_id'] ?>, 'expenses')">
                                                <i class="bi bi-pencil"></i>
                                            </button>
                                            <form method="POST" class="d-inline" onsubmit="return confirm('Delete this misc bill?');">
                                                <input type="hidden" name="action" value="delete_expense">
                                                <input type="hidden" name="current_tab" value="expenses">
                                                <input type="hidden" name="expense_id" value="<?= $ms['id'] ?>">
                                                <button type="submit" class="btn btn-sm btn-outline-danger py-0 px-1.5"><i class="bi bi-trash"></i></button>
                                            </form>
                                        </td>
                                        <?php endif; ?>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- TAB 4: SHOPPING ROSTER (WITH EDIT & DELETE) -->
        <div class="tab-pane fade <?= $activeTab === 'roster' ? 'show active' : '' ?>" id="tabRoster">
            <div class="card-clean p-4">
                <div class="mb-3">
                    <h5 class="fw-bold mb-0">Grocery Shopping Duty Schedule</h5>
                    <small class="text-muted">Assigned dates and responsible members for market shopping.</small>
                </div>

                <?php if ($role === 'manager'): ?>
                <form method="POST" class="row g-2 mb-4 p-3 bg-light rounded-3 border">
                    <input type="hidden" name="action" value="add_roster">
                    <input type="hidden" name="current_tab" value="roster">
                    <div class="col-md-3">
                        <select name="assigned_user_id" class="form-select">
                            <?php foreach ($members as $m): ?>
                                <option value="<?= $m['id'] ?>"><?= e($m['full_name']) ?> (<?= e($m['member_code']) ?>)</option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <input type="date" name="duty_date" required value="<?= e($today) ?>" class="form-control">
                    </div>
                    <div class="col-md-4">
                        <input type="text" name="items_note" placeholder="Shopping note (e.g., Weekly meat & fish)" class="form-control">
                    </div>
                    <div class="col-md-2">
                        <button type="submit" class="btn btn-dark w-100 fw-semibold">Assign Duty</button>
                    </div>
                </form>
                <?php endif; ?>

                <div class="table-responsive">
                    <table class="table align-middle mb-0">
                        <thead class="table-light small text-uppercase">
                            <tr>
                                <th>Date</th>
                                <th>Responsible Member</th>
                                <th>Notes</th>
                                <th>Status</th>
                                <?php if ($role === 'manager'): ?><th class="text-end">Actions</th><?php endif; ?>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($rosters as $r): ?>
                            <tr>
                                <td class="fw-semibold"><?= e($r['duty_date']) ?></td>
                                <td><?= e($r['full_name']) ?> <span class="badge bg-light text-dark border font-monospace"><?= e($r['member_code']) ?></span></td>
                                <td class="text-muted"><?= e($r['items_note'] ?: 'Regular grocery bazar') ?></td>
                                <td>
                                    <span class="badge <?= $r['status'] === 'completed' ? 'bg-success' : 'bg-warning text-dark' ?>">
                                        <?= ucfirst(e($r['status'])) ?>
                                    </span>
                                </td>
                                <?php if ($role === 'manager'): ?>
                                <td class="text-end text-nowrap">
                                    <form method="POST" class="d-inline">
                                        <input type="hidden" name="action" value="toggle_roster">
                                        <input type="hidden" name="current_tab" value="roster">
                                        <input type="hidden" name="roster_id" value="<?= $r['id'] ?>">
                                        <button type="submit" class="btn btn-outline-success btn-sm py-1 px-2">Toggle Done</button>
                                    </form>
                                    <button type="button" class="btn btn-outline-secondary btn-sm py-1 px-2"
                                        onclick="openEditRoster(<?= $r['id'] ?>, <?= (int)$r['assigned_user_id'] ?>, '<?= e($r['duty_date']) ?>', '<?= e($r['items_note']) ?>')">
                                        <i class="bi bi-pencil"></i>
                                    </button>
                                    <form method="POST" class="d-inline" onsubmit="return confirm('Delete this shopping duty?');">
                                        <input type="hidden" name="action" value="delete_roster">
                                        <input type="hidden" name="current_tab" value="roster">
                                        <input type="hidden" name="roster_id" value="<?= $r['id'] ?>">
                                        <button type="submit" class="btn btn-outline-danger btn-sm py-1 px-2"><i class="bi bi-trash"></i></button>
                                    </form>
                                </td>
                                <?php endif; ?>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- TAB 5: PREVIOUS MONTH INDIVIDUAL CALCULATION & CARRYOVER -->
        <div class="tab-pane fade <?= $activeTab === 'prevmonth' ? 'show active' : '' ?>" id="tabPrevMonth">
            <div class="card-clean overflow-hidden">
                <div class="p-4 d-flex justify-content-between align-items-center flex-wrap gap-3">
                    <div>
                        <h5 class="fw-bold mb-1">Previous Month Calculation (<?= e($prevMonth) ?>)</h5>
                        <p class="small text-muted mb-0">
                            Meal Rate: <strong><?= e($cur) ?><?= number_format($prevSummary['cost_per_meal'], 2) ?></strong> •
                            Total Meals: <strong><?= number_format($prevSummary['total_meals'], 1) ?></strong> •
                            Misc Share: <strong><?= e($cur) ?><?= number_format($prevSummary['misc_per_member'], 2) ?></strong>
                        </p>
                    </div>
                    <?php if ($role === 'manager'): ?>
                    <form method="POST" onsubmit="return confirm('Copy all closing net balances from <?= e($prevMonth) ?> into each member\'s Previous Balance?');">
                        <input type="hidden" name="action" value="carry_forward_prev">
                        <input type="hidden" name="current_tab" value="overview">
                        <button type="submit" class="btn btn-success fw-semibold">
                            <i class="bi bi-arrow-right-circle me-1"></i> Carry Forward Closing Balances to Current Month
                        </button>
                    </form>
                    <?php endif; ?>
                </div>
                <div class="table-responsive">
                    <table class="table table-ledger mb-0">
                        <thead>
                            <tr>
                                <th class="ps-4">Member</th>
                                <th class="text-end">Opening Bal</th>
                                <th class="text-end">Deposited</th>
                                <th class="text-end">Own Grocery</th>
                                <th class="text-end">Meals</th>
                                <th class="text-end">Meal Cost</th>
                                <th class="text-end">Misc Share</th>
                                <th class="text-end pe-4">Closing Net Balance</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($members as $m):
                                $uid = (int)$m['id'];
                                $pd  = $prevSummary['members'][$uid];
                            ?>
                            <tr>
                                <td class="ps-4 fw-bold"><?= e($m['full_name']) ?> <span class="badge bg-white text-secondary border font-monospace"><?= e($m['member_code']) ?></span></td>
                                <td class="text-end font-monospace"><?= ($pd['prev'] >= 0 ? '+' : '') . number_format($pd['prev'], 2) ?></td>
                                <td class="text-end font-monospace text-success"><?= number_format($pd['deposit'], 2) ?></td>
                                <td class="text-end font-monospace"><?= number_format($pd['grocery'], 2) ?></td>
                                <td class="text-end fw-bold"><?= number_format($pd['meals'], 1) ?></td>
                                <td class="text-end font-monospace"><?= number_format($pd['meal_cost'], 2) ?></td>
                                <td class="text-end font-monospace text-muted"><?= number_format($pd['misc'], 2) ?></td>
                                <td class="text-end pe-4">
                                    <span class="net-pill <?= $pd['net'] >= 0 ? 'bg-success-subtle text-success' : 'bg-danger-subtle text-danger' ?>">
                                        <?= ($pd['net'] >= 0 ? '+' : '') . e($cur) . number_format($pd['net'], 2) ?>
                                    </span>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

    </div>
</div>

<!-- OFFCANVAS DRAWER: CHAT & MANAGER APPROVALS -->
<div class="offcanvas offcanvas-end" tabindex="-1" id="drawerNotify" style="width: 420px;">
    <div class="offcanvas-header border-bottom">
        <h6 class="offcanvas-title fw-bold"><i class="bi bi-chat-dots-fill text-success me-1"></i> Mess Chat & Approvals</h6>
        <button type="button" class="btn-close" data-bs-dismiss="offcanvas"></button>
    </div>
    <div class="offcanvas-body d-flex flex-column justify-content-between">
        <div class="overflow-y-auto pe-1">
            <?php if ($role === 'manager' && count($pendingExpenses) > 0): ?>
                <div class="mb-4">
                    <div class="small fw-bold text-uppercase text-danger mb-2">Pending Expense Approvals (<?= count($pendingExpenses) ?>)</div>
                    <?php foreach ($pendingExpenses as $pe): ?>
                    <div class="p-3 mb-2 rounded-3 border border-warning bg-warning-subtle small">
                        <div class="d-flex justify-content-between fw-bold">
                            <span><?= e($pe['full_name']) ?> (<?= strtoupper($pe['category']) ?>)</span>
                            <span><?= e($cur) ?><?= number_format($pe['amount'], 2) ?></span>
                        </div>
                        <div class="text-dark my-1"><?= e($pe['description']) ?></div>
                        <form method="POST" class="d-flex gap-2 mt-2">
                            <input type="hidden" name="action" value="decide_expense">
                            <input type="hidden" name="expense_id" value="<?= $pe['id'] ?>">
                            <button name="decision" value="approved" class="btn btn-success btn-sm py-0 px-2">Approve</button>
                            <button name="decision" value="rejected" class="btn btn-outline-danger btn-sm py-0 px-2">Reject</button>
                        </form>
                    </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <div class="small fw-bold text-uppercase text-muted mb-2">Mess Chat & Meal Notices</div>
            <?php foreach ($chats as $c): $isMealReq = $c['request_type'] === 'meal_request'; ?>
                <div class="p-3 mb-2 rounded-3 border <?= ($isMealReq && !$c['is_processed']) ? 'border-primary bg-primary-subtle' : 'bg-light' ?> small">
                    <div class="d-flex justify-content-between align-items-center">
                        <strong><?= e($c['full_name']) ?> <span class="text-muted fw-normal">(<?= e($c['member_code']) ?>)</span></strong>
                        <?php if ($isMealReq): ?>
                            <span class="badge <?= $c['is_processed'] ? 'bg-secondary' : 'bg-primary' ?>">
                                <?= $c['is_processed'] ? 'Updated' : 'Meal Notice' ?>
                            </span>
                        <?php endif; ?>
                    </div>
                    <p class="mb-1 mt-1"><?= e($c['message']) ?></p>
                    <?php if ($role === 'manager' && $isMealReq && !$c['is_processed']): ?>
                        <form method="POST" class="mt-1">
                            <input type="hidden" name="action" value="mark_chat_done">
                            <input type="hidden" name="chat_id" value="<?= $c['id'] ?>">
                            <button type="submit" class="btn btn-dark btn-sm py-0 px-2" style="font-size:0.75rem;">Mark Meal Updated</button>
                        </form>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>

        <form method="POST" class="pt-3 border-top mt-3">
            <input type="hidden" name="action" value="send_chat">
            <div class="input-group mb-2">
                <select name="request_type" class="form-select" style="max-width: 135px;">
                    <option value="meal_request">Meal Notice</option>
                    <option value="chat">General Chat</option>
                </select>
                <input type="text" name="message" required class="form-control" placeholder="e.g., Off for dinner tonight...">
                <button type="submit" class="btn btn-success fw-semibold">Send</button>
            </div>
        </form>
    </div>
</div>

<!-- MODAL: ADD MEMBER -->
<div class="modal fade" id="modalMember" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <form method="POST" class="modal-content rounded-4">
            <div class="modal-header"><h6 class="modal-title fw-bold">Add New Mess Member</h6><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <div class="modal-body">
                <input type="hidden" name="action" value="add_member">
                <div class="mb-3"><label class="form-label small fw-semibold">Full Name</label><input type="text" name="full_name" required class="form-control"></div>
                <div class="mb-3"><label class="form-label small fw-semibold">Previous Month Balance (+ Credit / - Due)</label><input type="number" step="0.01" name="previous_balance" value="0" class="form-control"></div>
                <div><label class="form-label small fw-semibold">Temporary Login PIN</label><input type="text" name="pin" value="123456" required class="form-control"></div>
            </div>
            <div class="modal-footer"><button type="submit" class="btn btn-dark fw-semibold">Create Member</button></div>
        </form>
    </div>
</div>

<!-- MODAL: EDIT MEMBER -->
<div class="modal fade" id="modalEditMember" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <form method="POST" class="modal-content rounded-4">
            <div class="modal-header"><h6 class="modal-title fw-bold">Edit Member Details</h6><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <div class="modal-body">
                <input type="hidden" name="action" value="edit_member">
                <input type="hidden" name="user_id" id="editMemberId">
                <div class="mb-3"><label class="form-label small fw-semibold">Full Name</label><input type="text" name="full_name" id="editMemberName" required class="form-control"></div>
                <div class="mb-3"><label class="form-label small fw-semibold">Previous Balance (+ Credit / - Due)</label><input type="number" step="0.01" name="previous_balance" id="editMemberPrev" required class="form-control"></div>
                <div><label class="form-label small fw-semibold">Reset PIN / Password (Leave blank to keep current)</label><input type="text" name="new_pin" placeholder="Optional new PIN" class="form-control"></div>
            </div>
            <div class="modal-footer"><button type="submit" class="btn btn-primary fw-semibold">Save Changes</button></div>
        </form>
    </div>
</div>

<!-- MODAL: RECORD DEPOSIT -->
<div class="modal fade" id="modalDeposit" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <form method="POST" class="modal-content rounded-4">
            <div class="modal-header"><h6 class="modal-title fw-bold">Record Member Deposit</h6><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <div class="modal-body">
                <input type="hidden" name="action" value="add_deposit">
                <div class="mb-3">
                    <label class="form-label small fw-semibold">Select Member</label>
                    <select name="user_id" class="form-select">
                        <?php foreach ($members as $m): ?>
                            <option value="<?= $m['id'] ?>"><?= e($m['full_name']) ?> (<?= e($m['member_code']) ?>)</option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="mb-3"><label class="form-label small fw-semibold">Deposit Amount (<?= e($cur) ?>)</label><input type="number" step="0.01" name="amount" required class="form-control"></div>
                <div><label class="form-label small fw-semibold">Payment Note</label><input type="text" name="note" placeholder="e.g., Cash / bKash" class="form-control"></div>
            </div>
            <div class="modal-footer"><button type="submit" class="btn btn-success fw-semibold">Save Deposit</button></div>
        </form>
    </div>
</div>

<!-- MODAL: ONE-CLICK DEPOSIT CORRECTION -->
<div class="modal fade" id="modalFixDeposit" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <form method="POST" class="modal-content rounded-4">
            <div class="modal-header"><h6 class="modal-title fw-bold text-danger">Fix Wrong Deposit Amount</h6><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <div class="modal-body">
                <input type="hidden" name="action" value="correct_deposit">
                <input type="hidden" name="deposit_id" id="fixDepositId">
                <p class="small text-muted mb-3">Fixing deposit for <strong id="fixMemberName"></strong>. Wrong amount recorded: <strong id="fixWrongDisplay"></strong></p>
                <div class="mb-3">
                    <label class="form-label small fw-semibold">What is the ACTUAL correct deposit amount? (<?= e($cur) ?>)</label>
                    <input type="number" step="0.01" name="correct_amount" id="fixCorrectInput" required class="form-control form-control-lg fw-bold">
                </div>
                <div>
                    <label class="form-label small fw-semibold">Reason for Correction</label>
                    <input type="text" name="reason" required placeholder="e.g., Mistyped 5000 instead of 3000" class="form-control">
                </div>
            </div>
            <div class="modal-footer"><button type="submit" class="btn btn-danger fw-semibold">Apply Fix Automatically</button></div>
        </form>
    </div>
</div>

<!-- MODAL: EDIT EXPENSE (GROCERY / MISC) -->
<div class="modal fade" id="modalEditExpense" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <form method="POST" class="modal-content rounded-4">
            <div class="modal-header"><h6 class="modal-title fw-bold">Edit Expense Entry</h6><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <div class="modal-body">
                <input type="hidden" name="action" value="edit_expense">
                <input type="hidden" name="current_tab" value="expenses">
                <input type="hidden" name="expense_id" id="editExpId">
                <div class="mb-3">
                    <label class="form-label small fw-semibold">Paid By</label>
                    <select name="paid_by" id="editExpPaidBy" class="form-select">
                        <?php foreach ($members as $m): ?>
                            <option value="<?= $m['id'] ?>"><?= e($m['full_name']) ?> (<?= e($m['member_code']) ?>)</option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="mb-3"><label class="form-label small fw-semibold">Description</label><input type="text" name="description" id="editExpDesc" required class="form-control"></div>
                <div><label class="form-label small fw-semibold">Amount (<?= e($cur) ?>)</label><input type="number" step="0.01" name="amount" id="editExpAmount" required class="form-control"></div>
            </div>
            <div class="modal-footer"><button type="submit" class="btn btn-primary fw-semibold">Update Entry</button></div>
        </form>
    </div>
</div>

<!-- MODAL: EDIT SHOPPING ROSTER -->
<div class="modal fade" id="modalEditRoster" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <form method="POST" class="modal-content rounded-4">
            <div class="modal-header"><h6 class="modal-title fw-bold">Edit Shopping Duty</h6><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <div class="modal-body">
                <input type="hidden" name="action" value="edit_roster">
                <input type="hidden" name="current_tab" value="roster">
                <input type="hidden" name="roster_id" id="editRosterId">
                <div class="mb-3">
                    <label class="form-label small fw-semibold">Assigned Member</label>
                    <select name="assigned_user_id" id="editRosterUid" class="form-select">
                        <?php foreach ($members as $m): ?>
                            <option value="<?= $m['id'] ?>"><?= e($m['full_name']) ?> (<?= e($m['member_code']) ?>)</option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="mb-3"><label class="form-label small fw-semibold">Duty Date</label><input type="date" name="duty_date" id="editRosterDate" required class="form-control"></div>
                <div><label class="form-label small fw-semibold">Notes</label><input type="text" name="items_note" id="editRosterNote" class="form-control"></div>
            </div>
            <div class="modal-footer"><button type="submit" class="btn btn-dark fw-semibold">Save Duty</button></div>
        </form>
    </div>
</div>

<!-- MODAL: SUBSCRIPTION PLANS -->
<div class="modal fade" id="modalSub" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content rounded-4 p-3">
            <div class="modal-header border-0"><h6 class="modal-title fw-bold">Subscription Plans</h6><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <div class="modal-body">
                <div class="row g-3">
                    <div class="col-6">
                        <form method="POST" class="border rounded-3 p-3 h-100 d-flex flex-column justify-content-between">
                            <input type="hidden" name="action" value="activate_plan"><input type="hidden" name="plan" value="starter">
                            <div><h6 class="fw-bold">Starter Mess</h6><div class="fs-4 fw-bold text-success">৳199<small class="fs-6 text-muted">/mo</small></div><p class="small text-muted mt-1">Up to 12 members</p></div>
                            <button type="submit" class="btn btn-dark btn-sm w-100">Activate Starter</button>
                        </form>
                    </div>
                    <div class="col-6">
                        <form method="POST" class="border border-2 border-success rounded-3 p-3 h-100 d-flex flex-column justify-content-between">
                            <input type="hidden" name="action" value="activate_plan"><input type="hidden" name="plan" value="pro">
                            <div><h6 class="fw-bold">Hostel Pro</h6><div class="fs-4 fw-bold text-success">৳499<small class="fs-6 text-muted">/mo</small></div><p class="small text-muted mt-1">Up to 50 members</p></div>
                            <button type="submit" class="btn btn-success btn-sm w-100">Activate Pro</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
function openFixModal(depositId, memberName, wrongAmount) {
    document.getElementById('fixDepositId').value = depositId;
    document.getElementById('fixMemberName').textContent = memberName;
    document.getElementById('fixWrongDisplay').textContent = '<?= e($cur) ?>' + wrongAmount.toFixed(2);
    document.getElementById('fixCorrectInput').value = wrongAmount;
    new bootstrap.Modal(document.getElementById('modalFixDeposit')).show();
}
function openEditMember(uid, name, prevBal) {
    document.getElementById('editMemberId').value = uid;
    document.getElementById('editMemberName').value = name;
    document.getElementById('editMemberPrev').value = prevBal;
    new bootstrap.Modal(document.getElementById('modalEditMember')).show();
}
function openEditExpense(expId, desc, amount, paidBy) {
    document.getElementById('editExpId').value = expId;
    document.getElementById('editExpDesc').value = desc;
    document.getElementById('editExpAmount').value = amount;
    document.getElementById('editExpPaidBy').value = paidBy;
    new bootstrap.Modal(document.getElementById('modalEditExpense')).show();
}
function openEditRoster(rosterId, uid, dutyDate, note) {
    document.getElementById('editRosterId').value = rosterId;
    document.getElementById('editRosterUid').value = uid;
    document.getElementById('editRosterDate').value = dutyDate;
    document.getElementById('editRosterNote').value = note;
    new bootstrap.Modal(document.getElementById('modalEditRoster')).show();
}
</script>
</body>
</html>